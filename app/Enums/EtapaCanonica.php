<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Etapas comuns a todos os médicos. O mapa para os status_id de cada
 * pipeline fica em config/etapas.php (ver App\Support\MapaDeEtapas).
 * A ordem dos cases é a ordem do funil.
 */
enum EtapaCanonica: string implements HasColor, HasLabel
{
    case Novo = 'novo';
    case EmAtendimento = 'em_atendimento';
    case Fup1 = 'fup_1';
    case Fup2 = 'fup_2';
    case Fup3 = 'fup_3';
    case NaoAgendou = 'nao_agendou';
    case AguardandoSinal = 'aguardando_sinal';
    case ConsultaAgendada = 'consulta_agendada';
    case ConsultaRealizada = 'consulta_realizada';
    case Negociacao = 'negociacao';
    case CirurgiaAgendada = 'cirurgia_agendada';
    case Contratado = 'contratado';
    case Perdido = 'perdido';
    case Acompanhamento = 'acompanhamento';
    case VendaFutura = 'venda_futura';

    public function getLabel(): string
    {
        return match ($this) {
            self::Novo => 'Novo',
            self::EmAtendimento => 'Em atendimento',
            self::Fup1 => 'FUP 1',
            self::Fup2 => 'FUP 2',
            self::Fup3 => 'FUP 3',
            self::NaoAgendou => 'Não agendou',
            self::AguardandoSinal => 'Aguardando sinal',
            self::ConsultaAgendada => 'Consulta agendada',
            self::ConsultaRealizada => 'Consulta realizada',
            self::Negociacao => 'Orçamento / negociação',
            self::CirurgiaAgendada => 'Cirurgia / procedimento agendado',
            self::Contratado => 'Contratado',
            self::Perdido => 'Perdido',
            self::Acompanhamento => 'Acompanhamento',
            self::VendaFutura => 'Venda futura',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Fup1, self::Fup2, self::Fup3, self::NaoAgendou, self::AguardandoSinal => 'warning',
            self::ConsultaAgendada, self::ConsultaRealizada, self::Negociacao => 'info',
            self::CirurgiaAgendada, self::Contratado => 'success',
            self::Perdido => 'danger',
            default => 'gray',
        };
    }

    public function ehFup(): bool
    {
        return in_array($this, [self::Fup1, self::Fup2, self::Fup3], true);
    }

    /**
     * Etapas que formam o funil de venda, na ordem ("chegou até X").
     *
     * @return array<int, self>
     */
    public static function funil(): array
    {
        return [
            self::Novo,
            self::EmAtendimento,
            self::ConsultaAgendada,
            self::ConsultaRealizada,
            self::Negociacao,
            self::Contratado,
        ];
    }
}
