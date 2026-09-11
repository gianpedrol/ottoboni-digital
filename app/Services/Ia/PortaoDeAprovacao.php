<?php

namespace App\Services\Ia;

use App\Enums\IaGateModo;
use App\Enums\IaIntent;
use App\Enums\IaMotivoFila;
use App\Models\IaGateSetting;
use App\Services\Ia\DTO\DecisaoDoPortao;

/**
 * O portão. Decide, interação por interação, se a agente pode enviar sozinha.
 *
 * Ordem das regras (a ordem importa):
 *   1. assunto que sempre passa por humano  -> fila, em QUALQUER modo
 *   2. modo automático total                -> envia
 *   3. modo treinamento                     -> fila
 *   4. kill switch da nota geral            -> fila
 *   5. amostras insuficientes no assunto    -> fila
 *   6. acurácia abaixo do limiar / rejeição -> fila
 *   7. caso contrário                       -> envia
 *
 * A regra 1 é a que a Dra. Vanessa pediu: "não sei responder" nunca é
 * liberado, nem com 100% de acurácia. E qualquer falha inesperada cai em
 * fila — na dúvida a agente não fala.
 */
class PortaoDeAprovacao
{
    public function __construct(private readonly CalculadoraDeAcuracia $acuracia) {}

    public function decidir(int $doctorId, IaIntent $intent): DecisaoDoPortao
    {
        $cfg = IaGateSetting::paraAgente($doctorId);

        // 1. assuntos que nunca são liberados
        if (in_array($intent->value, $cfg->sempreRevisa(), true)) {
            return $this->fila(
                $cfg,
                $intent === IaIntent::NaoSei ? IaMotivoFila::NaoSei : IaMotivoFila::IntentSempreRevisa,
            );
        }

        // 2. atalho de emergência / demo
        if ($cfg->modo === IaGateModo::AutoTotal) {
            return new DecisaoDoPortao(true, IaMotivoFila::Auto, $cfg->modelo);
        }

        // 3. começa sempre aqui: tudo passa por humano
        if ($cfg->modo === IaGateModo::Treinamento) {
            return $this->fila($cfg, IaMotivoFila::ModoTreinamento);
        }

        // 4. freio de mão: se a nota geral caiu, tudo volta para a fila sozinho
        $geral = $this->acuracia->geral($doctorId, $cfg->janela);

        if ($geral['acuracia'] !== null && $geral['acuracia'] < $cfg->kill_switch_acuracia) {
            return $this->fila($cfg, IaMotivoFila::AcuraciaInsuficiente, [
                'acuracia_geral' => $geral['acuracia'],
                'kill_switch' => $cfg->kill_switch_acuracia,
            ]);
        }

        // 5. e 6. o assunto precisa ter provado que funciona
        $dados = $this->acuracia->porIntent($doctorId, $intent, $cfg->janela);

        if ($dados['amostras'] < $cfg->min_amostras) {
            return $this->fila($cfg, IaMotivoFila::AmostrasInsuficientes, [
                'amostras' => $dados['amostras'],
                'min_amostras' => $cfg->min_amostras,
            ]);
        }

        if ($dados['acuracia'] === null
            || $dados['acuracia'] < $cfg->limiar_acuracia
            || $dados['rejeitadas'] > 0) {
            return $this->fila($cfg, IaMotivoFila::AcuraciaInsuficiente, [
                'acuracia' => $dados['acuracia'],
                'rejeitadas' => $dados['rejeitadas'],
                'limiar' => $cfg->limiar_acuracia,
            ]);
        }

        // 7. liberado
        return new DecisaoDoPortao(true, IaMotivoFila::Auto, $cfg->modelo, detalhes: [
            'acuracia' => $dados['acuracia'],
            'amostras' => $dados['amostras'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $detalhes
     */
    private function fila(IaGateSetting $cfg, IaMotivoFila $motivo, array $detalhes = []): DecisaoDoPortao
    {
        return new DecisaoDoPortao(
            enviarDireto: false,
            motivo: $motivo,
            modelo: $cfg->modelo,
            timeoutMin: $cfg->timeout_min,
            dmEspera: $cfg->dm_espera_ativa,
            dmEsperaTexto: $cfg->dm_espera_texto ?? IaGateSetting::DM_ESPERA_PADRAO,
            detalhes: $detalhes,
        );
    }
}
