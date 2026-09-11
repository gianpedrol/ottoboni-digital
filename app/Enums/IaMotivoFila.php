<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Por que este item precisou de humano. Aparece no card da fila para a
 * pessoa entender em 1 segundo o que está olhando.
 */
enum IaMotivoFila: string implements HasColor, HasLabel
{
    case ModoTreinamento = 'modo_treinamento';
    case NaoSei = 'nao_sei';
    case BaixaConfianca = 'baixa_confianca';
    case AcuraciaInsuficiente = 'acuracia_insuficiente';
    case AmostrasInsuficientes = 'amostras_insuficientes';
    case IntentSempreRevisa = 'intent_sempre_revisa';
    case ErroBase = 'erro_base';
    case Auto = 'auto';

    public function getLabel(): string
    {
        return match ($this) {
            self::ModoTreinamento => 'Modo treinamento',
            self::NaoSei => 'A agente não soube responder',
            self::BaixaConfianca => 'Confiança baixa',
            self::AcuraciaInsuficiente => 'Acurácia ainda abaixo do limiar',
            self::AmostrasInsuficientes => 'Poucas amostras para liberar',
            self::IntentSempreRevisa => 'Assunto que sempre passa por humano',
            self::ErroBase => 'Falha ao consultar a base',
            self::Auto => 'Enviado automático',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NaoSei, self::ErroBase => 'danger',
            self::BaixaConfianca, self::IntentSempreRevisa => 'warning',
            self::Auto => 'success',
            default => 'gray',
        };
    }
}
