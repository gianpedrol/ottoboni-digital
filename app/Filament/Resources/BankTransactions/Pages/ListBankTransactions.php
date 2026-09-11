<?php

namespace App\Filament\Resources\BankTransactions\Pages;

use App\Filament\Concerns\ComSeloDemonstracao;
use App\Filament\Resources\BankTransactions\BankTransactionResource;
use App\Filament\Resources\BankTransactions\Widgets\BankAccountsStats;
use App\Models\BankAccount;
use App\Services\Financeiro\ImportadorOfx;
use App\Support\Financeiro;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListBankTransactions extends ListRecords
{
    use ComSeloDemonstracao;

    protected static string $resource = BankTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importarOfx')
                ->label('Importar OFX')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalHeading('Importar extrato OFX')
                ->modalDescription('Lançamentos já importados (mesmo FITID na mesma conta) são ignorados, então pode reenviar o arquivo sem medo.')
                ->modalSubmitActionLabel('Importar')
                ->fillForm(fn (): array => [
                    'bank_account_id' => BankAccount::query()->orderBy('id')->value('id'),
                ])
                ->schema([
                    Select::make('bank_account_id')
                        ->label('Conta bancária')
                        ->options(fn (): array => BankAccount::query()
                            ->orderBy('apelido')
                            ->get()
                            ->mapWithKeys(fn (BankAccount $c): array => [$c->id => $c->rotulo()])
                            ->all())
                        ->required()
                        ->createOptionForm([
                            TextInput::make('apelido')->label('Apelido')->required(),
                            TextInput::make('banco')->label('Banco')->required(),
                            TextInput::make('agencia')->label('Agência'),
                            TextInput::make('conta')->label('Conta'),
                            TextInput::make('saldo_inicial')->label('Saldo inicial')->numeric()->prefix('R$')->default(0),
                        ])
                        ->createOptionUsing(fn (array $data): int => BankAccount::query()->create([
                            ...$data,
                            'saldo_inicial' => (float) ($data['saldo_inicial'] ?? 0),
                            'demo' => Financeiro::registroDemo(),
                        ])->getKey()),
                    FileUpload::make('arquivo')
                        ->label('Arquivo .ofx')
                        ->storeFiles(false)
                        ->maxSize(2048)
                        ->required()
                        ->helperText('Exportado do internet banking. Para a demonstração: storage/app/demo/extrato-exemplo.ofx.'),
                ])
                ->action(function (array $data): void {
                    $arquivo = is_array($data['arquivo']) ? reset($data['arquivo']) : $data['arquivo'];

                    if (! $arquivo instanceof TemporaryUploadedFile
                        || mb_strtolower($arquivo->getClientOriginalExtension()) !== 'ofx') {
                        Notification::make()
                            ->danger()
                            ->title('Envie um arquivo .ofx')
                            ->send();

                        return;
                    }

                    $conta = BankAccount::query()->findOrFail($data['bank_account_id']);
                    $resultado = app(ImportadorOfx::class)->importar($conta, (string) $arquivo->get());

                    Notification::make()
                        ->success()
                        ->title("{$resultado['importados']} lançamentos importados")
                        ->body($resultado['ignorados'] > 0
                            ? "{$resultado['ignorados']} já estavam no extrato e foram ignorados."
                            : 'Use "Conciliar" em cada lançamento para dar baixa na parcela ou conta correspondente.')
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BankAccountsStats::class,
        ];
    }

    /**
     * Uma aba por conta bancária, com o nº de lançamentos não conciliados.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'todas' => Tab::make('Todas as contas'),
        ];

        foreach (BankAccount::query()->withCount(['transactions as pendentes_count' => fn (Builder $q) => $q->whereNull('conciliado_em')])->orderBy('id')->get() as $conta) {
            $tabs['conta-' . $conta->id] = Tab::make($conta->apelido)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('bank_account_id', $conta->id))
                ->badge($conta->getAttribute('pendentes_count') ?: null)
                ->badgeColor('warning')
                ->badgeTooltip('Lançamentos não conciliados');
        }

        return $tabs;
    }
}
