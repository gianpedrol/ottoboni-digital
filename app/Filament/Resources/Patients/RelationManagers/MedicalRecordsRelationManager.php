<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Enums\TipoRegistroProntuario;
use App\Filament\Resources\Patients\FichaDoPaciente;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Patients\Schemas\MedicalRecordForm;
use App\Models\AuditLog;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Prontuário do paciente, exibido dentro da aba "Prontuário" da ficha.
 * Só admin e gestor (no produto final: o médico responsável).
 */
class MedicalRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'medicalRecords';

    protected static ?string $title = 'Prontuário';

    protected static ?string $modelLabel = 'registro';

    protected static ?string $pluralModelLabel = 'registros';

    public function mount(): void
    {
        abort_unless(static::canViewForRecord($this->getOwnerRecord(), $this->getPageClass()), 403);

        parent::mount();

        AuditLog::registrar('abriu_prontuario', ['patient_id' => $this->getOwnerRecord()->getKey()]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return PatientResource::podeVerProntuario();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        /** @var Patient $patient */
        $patient = $this->getOwnerRecord();

        return MedicalRecordForm::configure($schema, $patient);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ViewEntry::make('detalhe')
                    ->hiddenLabel()
                    ->view('filament.pacientes.registro'),
            ]);
    }

    public function table(Table $table): Table
    {
        $tz = FichaDoPaciente::tz();

        return $table
            ->heading('Linha do tempo')
            ->description('Registros cifrados no banco. Registro assinado fica somente leitura.')
            ->recordTitleAttribute('titulo')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['autor', 'appointment']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i', $tz)
                    ->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('titulo')
                    ->label('Registro')
                    ->weight('medium')
                    ->description(fn (MedicalRecord $record): string => $record->resumo())
                    ->wrap(),
                TextColumn::make('autor.name')
                    ->label('Autor')
                    ->placeholder('—'),
                TextColumn::make('appointment.inicio')
                    ->label('Consulta')
                    ->date('d/m/Y', $tz)
                    ->placeholder('Sem vínculo'),
                TextColumn::make('situacao')
                    ->label('Assinatura')
                    ->state(fn (MedicalRecord $record): string => $record->estaAssinado() ? 'Assinado' : 'Rascunho')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Assinado' ? 'success' : 'warning')
                    ->icon(fn (string $state): Heroicon => $state === 'Assinado' ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedPencilSquare)
                    ->description(fn (MedicalRecord $record): ?string => $record->assinado_em?->timezone($tz)->format('d/m/Y H:i')),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(TipoRegistroProntuario::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Novo registro')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalHeading('Novo registro no prontuário')
                    ->modalWidth('4xl')
                    ->mutateDataUsing(fn (array $data): array => MedicalRecordForm::prepararDados([
                        ...$data,
                        'user_id' => Auth::id(),
                        'doctor_id' => $this->getOwnerRecord()->getAttribute('doctor_id'),
                    ]))
                    ->after(fn (MedicalRecord $record) => AuditLog::registrar('criou_registro_prontuario', [
                        'patient_id' => $record->patient_id,
                        'medical_record_id' => $record->id,
                        'tipo' => $record->tipo->value,
                    ])),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (MedicalRecord $record): string => $record->titulo)
                    ->modalWidth('3xl'),
                Action::make('imprimir')
                    ->label('Imprimir')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->visible(fn (MedicalRecord $record): bool => $record->tipo->imprimivel())
                    ->url(fn (MedicalRecord $record): string => PatientResource::getUrl('imprimir', [
                        'record' => $record->patient_id,
                        'registroId' => $record->id,
                    ]))
                    ->openUrlInNewTab(),
                Action::make('assinar')
                    ->label('Assinar')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (MedicalRecord $record): bool => Gate::allows('assinar', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Assinar registro')
                    ->modalDescription('Depois de assinado, o registro fica somente leitura: não pode ser editado nem excluído. No produto final a assinatura usa certificado digital ICP-Brasil (exigência do CFM para prontuário eletrônico).')
                    ->modalSubmitActionLabel('Assinar')
                    ->action(function (MedicalRecord $record): void {
                        Gate::authorize('assinar', $record);

                        $record->update(['assinado_em' => now()]);

                        AuditLog::registrar('assinou_registro_prontuario', [
                            'patient_id' => $record->patient_id,
                            'medical_record_id' => $record->id,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Registro assinado')
                            ->body('A partir de agora ele é somente leitura.')
                            ->send();
                    }),
                EditAction::make()
                    ->modalWidth('4xl')
                    ->mutateDataUsing(fn (array $data): array => MedicalRecordForm::prepararDados($data)),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Prontuário vazio')
            ->emptyStateDescription('Use "Novo registro" para anamnese, evolução, prescrição, pedido de exame, atestado ou fotos.');
    }
}
