<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Filament\Resources\Patients\FichaDoPaciente;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Patients\RelationManagers\MedicalRecordsRelationManager;
use App\Models\Patient;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $tz = FichaDoPaciente::tz();

        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Ficha do paciente')
                    ->persistTabInQueryString('aba')
                    ->tabs([
                        Tab::make('Resumo')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 3])
                                    ->schema([
                                        Section::make('Dados pessoais')
                                            ->columnSpan(['lg' => 2])
                                            ->columns(2)
                                            ->schema([
                                                TextEntry::make('nome')
                                                    ->label('Nome'),
                                                TextEntry::make('telefone')
                                                    ->label('Telefone')
                                                    ->copyable(),
                                                TextEntry::make('email')
                                                    ->label('E-mail')
                                                    ->placeholder('Não informado'),
                                                TextEntry::make('cpf_mascarado')
                                                    ->label('CPF')
                                                    ->state(fn (Patient $record): ?string => $record->cpfMascarado())
                                                    ->placeholder('Não informado'),
                                                TextEntry::make('data_nascimento')
                                                    ->label('Nascimento')
                                                    ->date('d/m/Y')
                                                    ->suffix(fn (Patient $record): ?string => $record->idade() !== null ? ' · ' . $record->idade() . ' anos' : null)
                                                    ->placeholder('Não informado'),
                                                TextEntry::make('sexo')
                                                    ->label('Sexo')
                                                    ->formatStateUsing(fn (?string $state): ?string => Patient::SEXOS[$state] ?? $state)
                                                    ->placeholder('Não informado'),
                                            ]),
                                        Section::make('Atendimento')
                                            ->columnSpan(['lg' => 1])
                                            ->schema([
                                                TextEntry::make('doctor.nome')
                                                    ->label('Médico')
                                                    ->placeholder('Sem médico'),
                                                TextEntry::make('procedimento_interesse')
                                                    ->label('Procedimento de interesse')
                                                    ->placeholder('Não informado'),
                                                TextEntry::make('origem')
                                                    ->label('Origem')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('Não informado'),
                                                TextEntry::make('indicacao')
                                                    ->label('Indicação')
                                                    ->placeholder('—'),
                                                TextEntry::make('created_at')
                                                    ->label('Cadastrado em')
                                                    ->dateTime('d/m/Y H:i', $tz),
                                            ]),
                                    ]),
                                Section::make('Observações')
                                    ->visible(fn (Patient $record): bool => filled($record->observacoes))
                                    ->schema([
                                        TextEntry::make('observacoes')
                                            ->hiddenLabel(),
                                    ]),
                                Section::make('Próximos agendamentos')
                                    ->schema([
                                        ViewEntry::make('proximos_agendamentos')
                                            ->hiddenLabel()
                                            ->view('filament.pacientes.agendamentos', [
                                                'somenteFuturos' => true,
                                                'vazio' => 'Nenhum agendamento futuro.',
                                            ]),
                                    ]),
                            ]),

                        Tab::make('Agenda')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->badge(fn (Patient $record): ?int => $record->appointments()->count() ?: null)
                            ->schema([
                                ViewEntry::make('agenda')
                                    ->hiddenLabel()
                                    ->view('filament.pacientes.agendamentos', [
                                        'somenteFuturos' => false,
                                        'vazio' => 'Nenhum agendamento para este paciente.',
                                    ]),
                            ]),

                        Tab::make('Prontuário')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->visible(fn (): bool => PatientResource::podeVerProntuario())
                            ->badge(fn (Patient $record): ?int => PatientResource::podeVerProntuario() ? ($record->medicalRecords()->count() ?: null) : null)
                            ->schema([
                                Livewire::make(
                                    MedicalRecordsRelationManager::class,
                                    fn (Patient $record): array => [
                                        'ownerRecord' => $record,
                                        'pageClass' => ViewPatient::class,
                                    ],
                                )->key('prontuario-do-paciente'),
                            ]),

                        Tab::make('Financeiro')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->visible(fn (): bool => PatientResource::podeVerFinanceiro())
                            ->schema([
                                ViewEntry::make('financeiro')
                                    ->hiddenLabel()
                                    ->view('filament.pacientes.financeiro'),
                            ]),

                        Tab::make('Kommo')
                            ->icon(Heroicon::OutlinedCloudArrowUp)
                            ->schema([
                                Section::make('Sincronização')
                                    ->columns(['default' => 2, 'lg' => 4])
                                    ->schema([
                                        TextEntry::make('kommo_sync_status')
                                            ->label('Status')
                                            ->badge(),
                                        TextEntry::make('kommo_contact_id')
                                            ->label('Contato no Kommo')
                                            ->placeholder('Ainda não criado'),
                                        TextEntry::make('kommo_lead_id')
                                            ->label('Atendimento (lead)')
                                            ->placeholder('Nenhum'),
                                        TextEntry::make('kommo_synced_at')
                                            ->label('Sincronizado em')
                                            ->dateTime('d/m/Y H:i', $tz)
                                            ->placeholder('Nunca'),
                                    ]),
                                Section::make('O que seria enviado ao Kommo')
                                    ->description('Requisições montadas pelo painel. No protótipo nada é enviado.')
                                    ->schema([
                                        ViewEntry::make('kommo_sync_payload')
                                            ->hiddenLabel()
                                            ->view('filament.pacientes.kommo-payload'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
