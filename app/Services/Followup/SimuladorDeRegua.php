<?php

namespace App\Services\Followup;

use App\Enums\FollowupCanal;
use App\Enums\FollowupModo;
use App\Models\FollowupPlan;
use App\Repositories\LeadRepository;
use App\Services\Kommo\DTO\LeadData;
use Carbon\CarbonImmutable;

/**
 * Renderiza a régua inteira para um lead real — datas e textos — SEM
 * enviar absolutamente nada. Ninguém publica régua no escuro (seção 7.4).
 */
class SimuladorDeRegua
{
    public function __construct(private readonly LeadRepository $repository) {}

    /**
     * @return array{lead: array<string, mixed>, passos: array<int, array<string, mixed>>}
     */
    public function simular(FollowupPlan $plan, int $leadId): array
    {
        $lead = $this->repository->find($leadId);

        if ($lead === null) {
            return ['lead' => [], 'passos' => [], 'erro' => 'Lead não encontrado no Kommo.'];
        }

        $tz = config('painel.timezone');
        $gatilhoEm = CarbonImmutable::now($tz);

        $passos = [];

        foreach ($plan->steps()->where('ativo', true)->get() as $step) {
            $previsto = JanelaDeEnvio::proxima($gatilhoEm->addHours($step->offset_horas));

            $observacoes = array_filter([
                $this->observacao($step->canal, $lead),
                $this->avisoDePlaceholders($step->modo, (string) $step->texto, $lead),
            ]);

            $passos[] = [
                'ordem' => $step->ordem,
                'offset_horas' => $step->offset_horas,
                'previsto_para' => $previsto->setTimezone($tz)->format('d/m/Y H:i (D)'),
                'canal' => $step->canal->getLabel(),
                'modo' => $step->modo->getLabel(),
                'texto' => $step->modo === FollowupModo::Ia
                    ? '[gerado pela IA na hora do envio, ancorado no lead] Prompt: ' . $step->prompt_ia
                    : TextoRenderer::render((string) $step->texto, $lead),
                'observacao' => $observacoes === [] ? null : implode(' ', $observacoes),
            ];
        }

        return [
            'lead' => [
                'id' => $lead->id,
                'nome' => $lead->name,
                'instagram' => $lead->instagramHandle(),
                'procedimento' => $lead->procedimento,
                'temperatura' => $lead->temperatura,
                'fechado' => $lead->ganho() || $lead->perdido(),
            ],
            'passos' => $passos,
        ];
    }

    private function avisoDePlaceholders(FollowupModo $modo, string $texto, LeadData $lead): ?string
    {
        if ($modo === FollowupModo::Ia) {
            return null;
        }

        $vazios = TextoRenderer::placeholdersSemValor($texto, $lead);

        if ($vazios === []) {
            return null;
        }

        return 'Este lead não tem ' . implode(', ', $vazios)
            . ' preenchido — a frase pode ficar manca. Revise o texto ou o cadastro do lead.';
    }

    private function observacao(FollowupCanal $canal, LeadData $lead): ?string
    {
        if (in_array($canal, [FollowupCanal::Auto, FollowupCanal::Instagram], true)
            && $lead->instagramHandle() === null) {
            return 'Lead sem @ do Instagram: este passo vai cair para o Kommo.';
        }

        if ($canal === FollowupCanal::Instagram) {
            return 'DM só sai se a última mensagem da pessoa tiver menos de 24h (regra da Meta); fora disso cai para o Kommo.';
        }

        return null;
    }
}
