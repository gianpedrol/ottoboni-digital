<?php

namespace App\Filament\Resources\Payables\Schemas;

use App\Enums\CategoriaDespesa;
use App\Enums\StatusFinanceiro;
use App\Support\Financeiro;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conta a pagar')
                    ->schema([
                        TextInput::make('fornecedor')
                            ->label('Fornecedor')
                            ->required()
                            ->maxLength(255),
                        Select::make('categoria')
                            ->label('Categoria')
                            ->options(CategoriaDespesa::class)
                            ->required(),
                        TextInput::make('descricao')
                            ->label('Descrição')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('valor')
                            ->label('Valor')
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('R$')
                            ->required(),
                        DatePicker::make('vencimento')
                            ->label('Vencimento')
                            ->displayFormat('d/m/Y')
                            ->default(fn (): string => Financeiro::hoje()->toDateString())
                            ->required(),
                        DatePicker::make('pago_em')
                            ->label('Pago em')
                            ->displayFormat('d/m/Y')
                            ->helperText('Deixe vazio enquanto não for paga.'),
                        Toggle::make('recorrente')
                            ->label('Recorrente (mensal)')
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Status acompanha a data de pagamento preenchida no formulário.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function ajustarStatus(array $data): array
    {
        $data['status'] = filled($data['pago_em'] ?? null)
            ? StatusFinanceiro::Pago->value
            : StatusFinanceiro::Aberto->value;

        return $data;
    }
}
