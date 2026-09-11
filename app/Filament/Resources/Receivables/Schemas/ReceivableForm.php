<?php

namespace App\Filament\Resources\Receivables\Schemas;

use App\Enums\FormaPagamento;
use App\Enums\OrigemRecebivel;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Service;
use App\Support\Financeiro;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReceivableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conta a receber')
                    ->schema([
                        Select::make('patient_id')
                            ->label('Paciente')
                            ->options(fn (): array => Patient::query()->orderBy('nome')->pluck('nome', 'id')->all())
                            ->searchable()
                            ->placeholder('Sem paciente vinculado'),
                        Select::make('doctor_id')
                            ->label('Médico')
                            ->options(Doctor::query()->orderBy('nome')->pluck('nome', 'id'))
                            ->required()
                            ->live(),
                        Select::make('service_id')
                            ->label('Serviço da tabela de preços')
                            ->options(fn ($get): array => Service::query()
                                ->ativos()
                                ->when($get('doctor_id'), fn ($q, $doctorId) => $q->where('doctor_id', $doctorId))
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                                ->all())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, $set, $get, string $operation): void {
                                $service = Service::query()->find($state);

                                if ($service === null) {
                                    return;
                                }

                                // Na criação, o serviço sugere descrição e valor (dá para ajustar).
                                if ($operation === 'create') {
                                    $set('valor_total', $service->valor);
                                }

                                if (blank($get('descricao'))) {
                                    $set('descricao', $service->nome);
                                }

                                $set('doctor_id', $service->doctor_id);
                            })
                            ->helperText('Opcional. Preenche descrição e valor.'),
                        TextInput::make('descricao')
                            ->label('Descrição')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('valor_total')
                            ->label('Valor total')
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('R$')
                            ->required()
                            ->live(onBlur: true)
                            ->disabledOn('edit')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? 'O valor é repartido nas parcelas; para mudar, ajuste as parcelas.'
                                : null),
                        Select::make('forma_pagamento')
                            ->label('Forma de pagamento')
                            ->options(FormaPagamento::class)
                            ->required(),
                        Select::make('origem')
                            ->label('Origem')
                            ->options(OrigemRecebivel::class)
                            ->default(OrigemRecebivel::Manual->value)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Parcelamento')
                    ->description('As parcelas são geradas ao salvar, com intervalo mensal.')
                    ->visibleOn('create')
                    ->schema([
                        TextInput::make('parcelas')
                            ->label('Nº de parcelas')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(24)
                            ->default(1)
                            ->required()
                            ->live(onBlur: true)
                            ->helperText(fn ($get): ?string => self::simulacao($get('valor_total'), $get('parcelas'))),
                        DatePicker::make('primeiro_vencimento')
                            ->label('1º vencimento')
                            ->default(fn (): string => Financeiro::hoje()->toDateString())
                            ->displayFormat('d/m/Y')
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    private static function simulacao(mixed $total, mixed $parcelas): ?string
    {
        if (! is_numeric($total) || ! is_numeric($parcelas) || (int) $parcelas < 1 || (float) $total <= 0) {
            return null;
        }

        $valores = Financeiro::dividir((float) $total, (int) $parcelas);
        $texto = count($valores) . 'x de ' . Financeiro::brl($valores[0]);

        $ultima = end($valores);

        if (count($valores) > 1 && $ultima !== $valores[0]) {
            $texto .= ' (última de ' . Financeiro::brl($ultima) . ')';
        }

        return $texto;
    }
}
