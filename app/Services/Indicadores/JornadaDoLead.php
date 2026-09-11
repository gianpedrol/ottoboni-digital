<?php

namespace App\Services\Indicadores;

use App\Enums\EtapaCanonica;
use App\Services\Kommo\DTO\LeadData;
use App\Support\MapaDeEtapas;
use Illuminate\Support\Collection;

/**
 * Até onde os leads de um período chegaram na jornada consulta → contrato.
 *
 * É uma leitura por coorte: pega os leads criados no período e olha a etapa
 * ATUAL de cada um no Kommo (a API não entrega o histórico sem /events).
 * Para não subestimar, campos preenchidos também contam: quem tem
 * "Próxima consulta" agendou; quem tem Comparecimento = compareceu realizou,
 * mesmo que depois tenha sido perdido.
 */
class JornadaDoLead
{
    private const AGENDOU = 2;

    private const REALIZOU = 3;

    private const NEGOCIOU = 4;

    private const CONTRATOU = 5;

    /**
     * @param  Collection<int, LeadData>  $leads
     * @return array{
     *     total: int, agendaram: int, realizaram: int, negociaram: int, contratados: int,
     *     em_negociacao: int, orcamentos_abertos_valor: float, contratados_valor: float,
     *     ticket_medio: ?float, em_fup: int, aguardando_sinal: int,
     *     compareceram: int, faltaram: int, comparecimento_pct: ?float,
     *     taxa_agendamento_pct: ?float, taxa_contratacao_pct: ?float
     * }
     */
    public function resumo(Collection $leads): array
    {
        $total = $leads->count();
        $posicoes = $leads->map(fn (LeadData $l): int => $this->posicao($l));

        $emNegociacao = $leads->filter(fn (LeadData $l): bool => $this->etapa($l) === EtapaCanonica::Negociacao);
        $contratados = $leads->filter(fn (LeadData $l): bool => $this->posicao($l) === self::CONTRATOU);
        $contratadosValor = (float) $contratados->sum(fn (LeadData $l): float => $this->valor($l));

        $compareceram = $leads->filter(fn (LeadData $l): bool => $this->compareceu($l))->count();
        $faltaram = $leads->filter(fn (LeadData $l): bool => $this->faltou($l))->count();

        $agendaram = $posicoes->filter(fn (int $p): bool => $p >= self::AGENDOU)->count();

        return [
            'total' => $total,
            'agendaram' => $agendaram,
            'realizaram' => $posicoes->filter(fn (int $p): bool => $p >= self::REALIZOU)->count(),
            'negociaram' => $posicoes->filter(fn (int $p): bool => $p >= self::NEGOCIOU)->count(),
            'contratados' => $contratados->count(),
            'em_negociacao' => $emNegociacao->count(),
            'orcamentos_abertos_valor' => (float) $emNegociacao->sum(fn (LeadData $l): float => $this->valor($l)),
            'contratados_valor' => $contratadosValor,
            'ticket_medio' => $contratados->isNotEmpty() ? round($contratadosValor / $contratados->count(), 2) : null,
            'em_fup' => $leads->filter(fn (LeadData $l): bool => (bool) $this->etapa($l)?->ehFup())->count(),
            'aguardando_sinal' => $leads->filter(fn (LeadData $l): bool => $this->etapa($l) === EtapaCanonica::AguardandoSinal)->count(),
            'compareceram' => $compareceram,
            'faltaram' => $faltaram,
            'comparecimento_pct' => $this->pct($compareceram, $compareceram + $faltaram),
            'taxa_agendamento_pct' => $this->pct($agendaram, $total),
            'taxa_contratacao_pct' => $this->pct($contratados->count(), $total),
        ];
    }

    /**
     * Quantos leads chegaram até cada etapa do funil (rótulo => quantidade).
     *
     * @param  Collection<int, LeadData>  $leads
     * @return array<string, int>
     */
    public function funil(Collection $leads): array
    {
        $posicoes = $leads->map(fn (LeadData $l): int => $this->posicao($l));
        $out = [];

        foreach (EtapaCanonica::funil() as $indice => $etapa) {
            $out[$etapa->getLabel()] = $posicoes->filter(fn (int $p): bool => $p >= $indice)->count();
        }

        return $out;
    }

    /**
     * Índice em EtapaCanonica::funil() até onde o lead comprovadamente chegou.
     */
    public function posicao(LeadData $lead): int
    {
        $pelaEtapa = match ($this->etapa($lead)) {
            EtapaCanonica::EmAtendimento, EtapaCanonica::Fup1, EtapaCanonica::Fup2, EtapaCanonica::Fup3,
            EtapaCanonica::NaoAgendou, EtapaCanonica::AguardandoSinal => 1,
            EtapaCanonica::ConsultaAgendada => self::AGENDOU,
            EtapaCanonica::ConsultaRealizada, EtapaCanonica::Acompanhamento => self::REALIZOU,
            EtapaCanonica::Negociacao, EtapaCanonica::VendaFutura => self::NEGOCIOU,
            EtapaCanonica::CirurgiaAgendada, EtapaCanonica::Contratado => self::CONTRATOU,
            // Perdido: a API não diz em que etapa foi perdido (docs/metricas.md)
            default => 0,
        };

        $pelosCampos = match (true) {
            $this->compareceu($lead) => self::REALIZOU,
            $lead->proximaConsulta !== null => self::AGENDOU,
            default => 0,
        };

        return max($pelaEtapa, $pelosCampos);
    }

    private function etapa(LeadData $lead): ?EtapaCanonica
    {
        return MapaDeEtapas::canonica($lead->pipelineId, $lead->statusId);
    }

    private function valor(LeadData $lead): float
    {
        return $lead->valorProposta ?? (float) $lead->price;
    }

    private function compareceu(LeadData $lead): bool
    {
        $v = mb_strtolower(trim((string) $lead->comparecimento));

        return $v !== '' && ! $this->faltou($lead) && (str_contains($v, 'compareceu') || $v === 'sim');
    }

    private function faltou(LeadData $lead): bool
    {
        $v = mb_strtolower(trim((string) $lead->comparecimento));

        return str_contains($v, 'falt') || str_contains($v, 'não compareceu') || str_contains($v, 'nao compareceu');
    }

    private function pct(int $parte, int $todo): ?float
    {
        return $todo > 0 ? round($parte / $todo * 100, 1) : null;
    }
}
