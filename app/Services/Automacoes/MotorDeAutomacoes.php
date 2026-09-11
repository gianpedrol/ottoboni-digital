<?php

namespace App\Services\Automacoes;

use App\Enums\EtapaCanonica;
use App\Enums\FollowupGatilho;
use App\Enums\FollowupRunStatus;
use App\Models\AutomationRule;
use App\Models\FollowupPlan;
use App\Models\FollowupRun;
use App\Services\Kommo\DTO\LeadData;
use App\Support\MapaDeEtapas;
use Carbon\CarbonImmutable;

/**
 * Motor de automações do Kommo (Fase 3, protótipo).
 *
 * Avalia uma regra contra um lead e devolve, passo a passo, o que faria —
 * incluindo a chamada exata que iria para a API do Kommo. É 100% dry-run:
 * não chama API, não grava nada, só lê o banco local (réguas da Fase 2).
 * Quem executa de verdade (fila + KommoClient) fica para a etapa 3B.
 *
 * ## Formato dos JSONs de AutomationRule
 *
 * Etapas são sempre canônicas (App\Enums\EtapaCanonica), traduzidas para
 * status_id por pipeline via App\Support\MapaDeEtapas. Campos usam as
 * chaves de App\Services\Automacoes\CamposDoLead.
 *
 * gatilho (um objeto; "ou" é opcional e lista gatilhos alternativos):
 *   {"tipo":"etapa","etapas":["fup_1"]}
 *   {"tipo":"campo_alterado","campo":"comparecimento","valor":"Compareceu"}
 *   {"tipo":"campo_preenchido","campo":"proxima_consulta"}
 *   {"tipo":"tag_adicionada","tag":"CONTRATO"}
 *   {"tipo":"inatividade","horas":48}
 *   {"tipo":"lead_criado"}
 *   {"tipo":"campo_preenchido","campo":"data_assinatura","ou":[{"tipo":"tag_adicionada","tag":"CONTRATO"}]}
 *
 * condicoes (lista; todas precisam ser verdadeiras, avaliadas depois do evento):
 *   {"tipo":"etapa_atual","etapas":["fup_1","fup_2"]}
 *   {"tipo":"campo_preenchido","campo":"proxima_consulta"}
 *   {"tipo":"campo_igual","campo":"status_sinal","valor":"Pago"}
 *   {"tipo":"tem_tag","tag":"SINAL"}
 *
 * acoes (lista, executadas em ordem; cada uma parte do estado deixado pela anterior):
 *   {"tipo":"mover_etapa","etapa":"consulta_agendada"}
 *   {"tipo":"adicionar_tag","tag":"Contato{fup}"}      {fup} = 1, 2 ou 3 conforme a etapa de FUP atual
 *   {"tipo":"remover_tags","tags":["Contato1","Contato2"]}  não remove tag que a própria regra acabou de pôr
 *   {"tipo":"preencher_campo","campo":"comparecimento","valor":"Compareceu"}
 *   {"tipo":"criar_tarefa","texto":"Iniciar pré-operatório","prazo_horas":24}
 *   {"tipo":"criar_card_pipeline","pipeline":"cirurgias","etapa":null,"copiar_campos":["cirurgia","valor_total"]}
 *        etapa = status_id no pipeline de destino; vazio = etapa de entrada
 *   {"tipo":"iniciar_regua","followup_plan_id":null}   vazio = régua cujo gatilho é a etapa atual do lead
 *   {"tipo":"cancelar_reguas"}
 *   {"tipo":"disparar_salesbot","bot_id":null}         vazio = Salesbot configurado no médico
 *   {"tipo":"criar_recebivel"}
 *
 * pipeline_ids: lista de pipelines onde a regra vale. Lead de outro
 * pipeline não casa.
 *
 * ## Anti-loop
 *
 * Tudo que o painel altera no Kommo volta como webhook. No motor real, cada
 * execução guarda lead + regra + updated_at do lead, e o evento cujo
 * modified_user_id é o usuário da integração é descartado antes de avaliar
 * qualquer regra. Aqui a checagem é a flag EventoSimulado::$geradoPelaIntegracao:
 * evento marcado assim é ignorado com MOTIVO_ANTI_LOOP. Além disso, ação que
 * não mudaria nada (lead já na etapa, tag já presente) sai como "ignorada",
 * então a mesma regra rodando duas vezes não gera uma segunda escrita.
 */
class MotorDeAutomacoes
{
    public const MOTIVO_ANTI_LOOP = 'Evento gerado pela própria integração (o painel alterou o lead) — ignorado para não entrar em loop.';

    private const SEM_EVENTO = ' (estado atual do lead, sem evento)';

    private NomesDoKommo $nomes;

    public function __construct(?NomesDoKommo $nomes = null)
    {
        $this->nomes = $nomes ?? new NomesDoKommo;
    }

    public function comNomes(NomesDoKommo $nomes): self
    {
        return new self($nomes);
    }

    public function nomes(): NomesDoKommo
    {
        return $this->nomes;
    }

    /**
     * @param  array<string, ?string>  $camposExtras  campos que o LeadData não carrega (Hospital, Prótese…)
     */
    public function avaliar(
        AutomationRule $regra,
        LeadData $lead,
        ?EventoSimulado $evento = null,
        array $camposExtras = [],
    ): ResultadoAvaliacao {
        $estado = EstadoDoLead::deLead($lead, $camposExtras);
        $pipelines = NormalizadorDeRegra::pipelines((array) $regra->pipeline_ids);
        $pipelineDoLead = $this->nomes->pipeline($estado->pipelineId);

        if (! in_array($estado->pipelineId, $pipelines, true)) {
            $nomes = array_map(fn (int $id): string => $this->nomes->pipeline($id), $pipelines);

            return new ResultadoAvaliacao(
                pipelineOk: false,
                pipelineMotivo: 'A regra vale para ' . (DescritorDeRegra::lista($nomes, 'e') ?: 'nenhum pipeline')
                    . "; o lead está no pipeline {$pipelineDoLead}.",
                ignoradoPorLoop: false,
                gatilhoCasou: false,
                gatilhoMotivo: 'Não avaliado: lead fora dos pipelines da regra.',
                condicoes: [],
                acoes: [],
                evento: $evento,
            );
        }

        $pipelineMotivo = "Lead no pipeline {$pipelineDoLead}, coberto pela regra.";

        if ($evento !== null && $evento->geradoPelaIntegracao) {
            return new ResultadoAvaliacao(true, $pipelineMotivo, true, false, 'Não avaliado (anti-loop).', [], [], $evento);
        }

        [$gatilhoCasou, $gatilhoMotivo] = $this->avaliarGatilho((array) $regra->gatilho, $estado, $evento);

        $condicoes = array_map(
            fn (array $c): ResultadoCondicao => $this->avaliarCondicao($c, $estado),
            NormalizadorDeRegra::condicoes($regra->condicoes),
        );

        $condicoesOk = array_reduce($condicoes, fn (bool $ok, ResultadoCondicao $c): bool => $ok && $c->ok, true);

        $acoes = $gatilhoCasou && $condicoesOk
            ? $this->simularAcoes(NormalizadorDeRegra::acoes($regra->acoes), $estado, $regra)
            : [];

        return new ResultadoAvaliacao(true, $pipelineMotivo, false, $gatilhoCasou, $gatilhoMotivo, $condicoes, $acoes, $evento);
    }

    // ---------------------------------------------------------------- gatilho

    /**
     * @param  array<string, mixed>  $gatilho
     * @return array{0: bool, 1: string}
     */
    private function avaliarGatilho(array $gatilho, EstadoDoLead $estado, ?EventoSimulado $evento): array
    {
        if ($evento !== null) {
            $erro = $this->aplicarEvento($evento, $estado);

            if ($erro !== null) {
                return [false, $erro];
            }
        }

        $alternativas = [NormalizadorDeRegra::gatilho($gatilho, false)];

        foreach ((array) ($gatilho['ou'] ?? []) as $alternativa) {
            if (is_array($alternativa)) {
                $alternativas[] = NormalizadorDeRegra::gatilho($alternativa, false);
            }
        }

        $motivos = [];

        foreach ($alternativas as $alternativa) {
            [$ok, $motivo] = $this->casaGatilho($alternativa, $estado, $evento);

            if ($ok) {
                return [true, $motivo];
            }

            $motivos[] = $motivo;
        }

        return [false, implode(' / ', $motivos)];
    }

    /**
     * Leva o evento para a cópia do lead: depois dele, as condições e as
     * ações enxergam o lead como o Kommo o deixou.
     */
    private function aplicarEvento(EventoSimulado $evento, EstadoDoLead $estado): ?string
    {
        switch ($evento->tipo) {
            case 'etapa':
                $etapa = EtapaCanonica::tryFrom((string) $evento->etapa);

                if ($etapa === null) {
                    return 'Evento sem etapa válida.';
                }

                if (! in_array($estado->statusId, MapaDeEtapas::statusIds($estado->pipelineId, $etapa), true)) {
                    $destino = MapaDeEtapas::destino($estado->pipelineId, $etapa);

                    if ($destino === null) {
                        return "A etapa {$etapa->getLabel()} não existe no pipeline {$this->nomes->pipeline($estado->pipelineId)}.";
                    }

                    $estado->statusId = $destino;
                }

                break;

            case 'campo_alterado':
                $estado->campos[(string) $evento->campo] = $evento->valor;
                break;

            case 'tag_adicionada':
                $estado->adicionarTag((string) $evento->tag);
                break;

            case 'inatividade':
                $estado->atualizadoEm = CarbonImmutable::now()->subHours((int) $evento->horas);
                break;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $g
     * @return array{0: bool, 1: string}
     */
    private function casaGatilho(array $g, EstadoDoLead $estado, ?EventoSimulado $evento): array
    {
        $tipo = (string) ($g['tipo'] ?? '');
        $campo = (string) ($g['campo'] ?? '');
        $rotulo = CamposDoLead::rotulo($campo);

        switch ($tipo) {
            case 'etapa':
                $etapas = array_map(strval(...), (array) ($g['etapas'] ?? []));
                $esperado = DescritorDeRegra::etapas($etapas);

                if ($evento !== null) {
                    if ($evento->tipo !== 'etapa') {
                        return [false, "O evento “{$evento->descricao()}” não é entrada em etapa; a regra espera entrada em {$esperado}."];
                    }

                    $rotuloEvento = EtapaCanonica::tryFrom((string) $evento->etapa)?->getLabel() ?? (string) $evento->etapa;

                    return in_array($evento->etapa, $etapas, true)
                        ? [true, "Entrou na etapa {$rotuloEvento} ({$this->nomes->status($estado->pipelineId, $estado->statusId)})."]
                        : [false, "O lead entrou em {$rotuloEvento}; a regra espera entrada em {$esperado}."];
                }

                $atual = $estado->etapa();

                return $atual !== null && in_array($atual->value, $etapas, true)
                    ? [true, "Lead está em {$this->etapaLegivel($estado)}" . self::SEM_EVENTO . '.']
                    : [false, "Lead está em {$this->etapaLegivel($estado)}; a regra espera entrada em {$esperado}."];

            case 'campo_alterado':
                $valor = (string) ($g['valor'] ?? '');

                if ($evento !== null) {
                    if ($evento->tipo !== 'campo_alterado' || $evento->campo !== $campo) {
                        return [false, "O evento “{$evento->descricao()}” não altera {$rotulo}."];
                    }

                    return self::iguais($evento->valor, $valor)
                        ? [true, "Campo {$rotulo} mudou para “{$evento->valor}”."]
                        : [false, "{$rotulo} mudou para “{$evento->valor}”, mas a regra espera “{$valor}”."];
                }

                $atual = $estado->campo($campo);

                return self::iguais($atual, $valor)
                    ? [true, "{$rotulo} = “{$atual}”" . self::SEM_EVENTO . '.']
                    : [false, "{$rotulo} está " . ($atual === null ? 'vazio' : "“{$atual}”") . "; a regra espera “{$valor}”."];

            case 'campo_preenchido':
                if ($evento !== null) {
                    if ($evento->tipo !== 'campo_alterado' || $evento->campo !== $campo) {
                        return [false, "O evento “{$evento->descricao()}” não preenche {$rotulo}."];
                    }

                    return filled($evento->valor)
                        ? [true, "{$rotulo} foi preenchido ({$evento->valor})."]
                        : [false, "{$rotulo} foi apagado, não preenchido."];
                }

                $atual = $estado->campo($campo);

                return $atual !== null
                    ? [true, "{$rotulo} já está preenchido ({$atual})" . self::SEM_EVENTO . '.']
                    : [false, "{$rotulo} está vazio."];

            case 'tag_adicionada':
                $tag = (string) ($g['tag'] ?? '');

                if ($evento !== null) {
                    if ($evento->tipo !== 'tag_adicionada') {
                        return [false, "O evento “{$evento->descricao()}” não adiciona a tag {$tag}."];
                    }

                    return EstadoDoLead::mesmaTag((string) $evento->tag, $tag)
                        ? [true, "Tag {$tag} adicionada."]
                        : [false, "Foi adicionada a tag {$evento->tag}; a regra espera {$tag}."];
                }

                return $estado->temTag($tag)
                    ? [true, "Lead tem a tag {$tag}" . self::SEM_EVENTO . '.']
                    : [false, "Lead não tem a tag {$tag}."];

            case 'inatividade':
                $horas = (int) ($g['horas'] ?? 0);

                if ($evento !== null && $evento->tipo !== 'inatividade') {
                    return [false, "O evento “{$evento->descricao()}” não é inatividade."];
                }

                if ($estado->atualizadoEm === null) {
                    return [false, 'Lead sem data de última atualização.'];
                }

                $parado = (int) floor(abs($estado->atualizadoEm->diffInHours(CarbonImmutable::now())));

                return $parado >= $horas
                    ? [true, "Lead parado há {$parado} h (a regra pede {$horas} h)."]
                    : [false, "Lead parado há {$parado} h; a regra pede {$horas} h."];

            case 'lead_criado':
                return $evento?->tipo === 'lead_criado'
                    ? [true, 'Lead criado.']
                    : [false, 'Este gatilho só dispara com o evento “lead criado”.'];
        }

        return [false, "Tipo de gatilho desconhecido: {$tipo}."];
    }

    // -------------------------------------------------------------- condições

    /**
     * @param  array<string, mixed>  $c
     */
    private function avaliarCondicao(array $c, EstadoDoLead $estado): ResultadoCondicao
    {
        $descricao = DescritorDeRegra::condicao($c);
        $campo = (string) ($c['campo'] ?? '');
        $rotulo = CamposDoLead::rotulo($campo);

        switch ($c['tipo'] ?? null) {
            case 'etapa_atual':
                $etapas = array_map(strval(...), (array) ($c['etapas'] ?? []));
                $atual = $estado->etapa();
                $ok = $atual !== null && in_array($atual->value, $etapas, true);

                return new ResultadoCondicao($descricao, $ok, "Lead está em {$this->etapaLegivel($estado)}"
                    . ($ok ? '.' : ', fora de ' . DescritorDeRegra::etapas($etapas) . '.'));

            case 'campo_preenchido':
                $valor = $estado->campo($campo);

                return new ResultadoCondicao($descricao, $valor !== null, $valor !== null
                    ? "{$rotulo} = {$valor}."
                    : "{$rotulo} está vazio.");

            case 'campo_igual':
                $esperado = (string) ($c['valor'] ?? '');
                $valor = $estado->campo($campo);
                $ok = self::iguais($valor, $esperado);

                return new ResultadoCondicao($descricao, $ok, "{$rotulo} está "
                    . ($valor === null ? 'vazio' : "“{$valor}”")
                    . ($ok ? '.' : " (esperado “{$esperado}”)."));

            case 'tem_tag':
                $tag = (string) ($c['tag'] ?? '');
                $ok = $estado->temTag($tag);

                return new ResultadoCondicao($descricao, $ok, $ok
                    ? "Lead tem a tag {$tag}."
                    : "Lead não tem a tag {$tag}" . ($estado->tags === [] ? ' (lead sem tags).' : ' (tags: ' . implode(', ', $estado->tags) . ').'));
        }

        return new ResultadoCondicao($descricao, false, 'Tipo de condição desconhecido.');
    }

    // ----------------------------------------------------------------- ações

    /**
     * @param  array<int, array<string, mixed>>  $acoes
     * @return array<int, AcaoSimulada>
     */
    private function simularAcoes(array $acoes, EstadoDoLead $estado, AutomationRule $regra): array
    {
        $resultado = [];

        foreach ($acoes as $acao) {
            $resultado[] = match ($acao['tipo'] ?? null) {
                'mover_etapa' => $this->moverEtapa($acao, $estado),
                'adicionar_tag' => $this->adicionarTag($acao, $estado),
                'remover_tags' => $this->removerTags($acao, $estado),
                'preencher_campo' => $this->preencherCampo($acao, $estado),
                'criar_tarefa' => $this->criarTarefa($acao, $estado),
                'criar_card_pipeline' => $this->criarCard($acao, $estado),
                'iniciar_regua' => $this->iniciarRegua($acao, $estado),
                'cancelar_reguas' => $this->cancelarReguas($estado, $regra),
                'disparar_salesbot' => $this->dispararSalesbot($acao, $estado),
                'criar_recebivel' => $this->criarRecebivel($estado, $regra),
                default => AcaoSimulada::ignorada((string) ($acao['tipo'] ?? '?'), 'Ação desconhecida', 'Tipo de ação não suportado pelo motor.'),
            };
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function moverEtapa(array $a, EstadoDoLead $estado): AcaoSimulada
    {
        $etapa = EtapaCanonica::tryFrom((string) ($a['etapa'] ?? ''));

        if ($etapa === null) {
            return AcaoSimulada::ignorada('mover_etapa', 'Mover de etapa', 'Etapa de destino não informada.');
        }

        $pipeline = $estado->pipelineId;
        $destino = MapaDeEtapas::destino($pipeline, $etapa);

        if ($destino === null) {
            return AcaoSimulada::ignorada('mover_etapa', "Mover para {$etapa->getLabel()}",
                "A etapa {$etapa->getLabel()} não existe no pipeline {$this->nomes->pipeline($pipeline)}.");
        }

        $de = $this->nomes->status($pipeline, $estado->statusId);

        if (in_array($estado->statusId, MapaDeEtapas::statusIds($pipeline, $etapa), true)) {
            return AcaoSimulada::ignorada('mover_etapa', "Mover para {$etapa->getLabel()}",
                "Lead já estava na etapa de destino ({$de}).");
        }

        $para = $this->nomes->status($pipeline, $destino);
        $estado->statusId = $destino;

        return new AcaoSimulada(
            tipo: 'mover_etapa',
            descricao: "Mover de {$de} para {$para}",
            metodo: 'PATCH',
            endpoint: "/api/v4/leads/{$estado->id}",
            payload: ['status_id' => $destino, 'pipeline_id' => $pipeline],
        );
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function adicionarTag(array $a, EstadoDoLead $estado): AcaoSimulada
    {
        $original = (string) ($a['tag'] ?? '');
        $tag = $this->resolverTag($original, $estado);

        if ($tag === null) {
            return AcaoSimulada::ignorada('adicionar_tag', "Adicionar a tag {$original}",
                'O lead não está numa etapa de FUP, então {fup} fica sem número.');
        }

        if ($tag === '') {
            return AcaoSimulada::ignorada('adicionar_tag', 'Adicionar tag', 'Tag não informada.');
        }

        // Mesmo já presente, a tag é "da regra": o remover_tags seguinte não a tira.
        $estado->tagsAdicionadas[] = $tag;

        if ($estado->temTag($tag)) {
            return AcaoSimulada::ignorada('adicionar_tag', "Adicionar a tag {$tag}", "Lead já tem a tag {$tag}.");
        }

        $estado->adicionarTag($tag);

        return new AcaoSimulada(
            tipo: 'adicionar_tag',
            descricao: "Adicionar a tag {$tag}",
            metodo: 'PATCH',
            endpoint: "/api/v4/leads/{$estado->id}",
            payload: $this->payloadTags($estado),
            observacao: 'O Kommo troca a lista inteira de tags: o payload leva todas as tags com que o lead fica.',
        );
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function removerTags(array $a, EstadoDoLead $estado): AcaoSimulada
    {
        $lista = array_values(array_filter(array_map(
            fn ($t): ?string => $this->resolverTag((string) $t, $estado),
            (array) ($a['tags'] ?? []),
        ), filled(...)));

        $descricao = 'Remover as tags ' . DescritorDeRegra::lista($lista, 'e');
        $remover = [];
        $mantidas = [];

        foreach ($lista as $tag) {
            if (! $estado->temTag($tag)) {
                continue;
            }

            $colocadaAgora = array_filter($estado->tagsAdicionadas, fn (string $t): bool => EstadoDoLead::mesmaTag($t, $tag));

            if ($colocadaAgora !== []) {
                $mantidas[] = $tag;
            } else {
                $remover[] = $tag;
            }
        }

        if ($remover === []) {
            return AcaoSimulada::ignorada('remover_tags', $descricao, $mantidas !== []
                ? 'Nada a tirar: ' . DescritorDeRegra::lista($mantidas, 'e') . ' acabou de ser colocada por esta regra.'
                : 'Nenhuma dessas tags está no lead.');
        }

        foreach ($remover as $tag) {
            $estado->removerTag($tag);
        }

        return new AcaoSimulada(
            tipo: 'remover_tags',
            descricao: (count($remover) === 1 ? 'Remover a tag ' : 'Remover as tags ') . DescritorDeRegra::lista($remover, 'e'),
            metodo: 'PATCH',
            endpoint: "/api/v4/leads/{$estado->id}",
            payload: $this->payloadTags($estado),
            observacao: $mantidas !== [] ? DescritorDeRegra::lista($mantidas, 'e') . ' fica (colocada por esta regra).' : null,
        );
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function preencherCampo(array $a, EstadoDoLead $estado): AcaoSimulada
    {
        $campo = (string) ($a['campo'] ?? '');
        $valor = (string) ($a['valor'] ?? '');
        $rotulo = CamposDoLead::rotulo($campo);

        if ($campo === '') {
            return AcaoSimulada::ignorada('preencher_campo', 'Preencher campo', 'Campo não informado.');
        }

        if (self::iguais($estado->campo($campo), $valor)) {
            return AcaoSimulada::ignorada('preencher_campo', "Preencher {$rotulo}", "{$rotulo} já está “{$valor}”.");
        }

        $estado->campos[$campo] = $valor;

        return new AcaoSimulada(
            tipo: 'preencher_campo',
            descricao: "Preencher {$rotulo} com “{$valor}”",
            metodo: 'PATCH',
            endpoint: "/api/v4/leads/{$estado->id}",
            payload: ['custom_fields_values' => [$this->campoPayload($campo, $valor)]],
        );
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function criarTarefa(array $a, EstadoDoLead $estado): AcaoSimulada
    {
        $texto = (string) ($a['texto'] ?? 'Tarefa da automação');
        $prazo = (int) ($a['prazo_horas'] ?? 24);
        $quando = CarbonImmutable::now()->addHours($prazo);
        $noCard = $estado->cardCriado;

        return new AcaoSimulada(
            tipo: 'criar_tarefa',
            descricao: "Criar tarefa “{$texto}” " . ($noCard ? 'no card do Fluxo cirurgias' : 'no lead')
                . ", prazo {$prazo} h (" . $quando->setTimezone((string) config('painel.timezone'))->format('d/m/Y H:i') . ')',
            metodo: 'POST',
            endpoint: '/api/v4/tasks',
            payload: [[
                'text' => $texto,
                'complete_till' => $quando->getTimestamp(),
                'entity_id' => $noCard ? '{id do card criado no passo anterior}' : $estado->id,
                'entity_type' => 'leads',
                'task_type_id' => 1,
            ]],
            observacao: $noCard ? 'O id do card só existe depois do POST /api/v4/leads acima; a tarefa é criada logo em seguida.' : null,
        );
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function criarCard(array $a, EstadoDoLead $estado): AcaoSimulada
    {
        $destino = $a['pipeline'] ?? 'cirurgias';
        $pipelineId = $destino === 'cirurgias' ? (int) config('kommo.pipeline_cirurgias') : (int) $destino;
        $statusId = isset($a['etapa']) && is_numeric($a['etapa']) ? (int) $a['etapa'] : null;
        $nomePipeline = $this->nomes->pipeline($pipelineId);

        $customFields = [];
        $copiados = [];
        $vazios = [];
        $naoLidos = [];

        foreach ((array) ($a['copiar_campos'] ?? []) as $campo) {
            $campo = (string) $campo;
            $valor = $estado->campo($campo);

            if ($valor !== null) {
                $customFields[] = $this->campoPayload($campo, $valor);
                $copiados[] = CamposDoLead::rotulo($campo);
            } elseif (in_array($campo, CamposDoLead::NAO_LIDOS, true)) {
                $naoLidos[] = CamposDoLead::rotulo($campo);
            } else {
                $vazios[] = CamposDoLead::rotulo($campo);
            }
        }

        $card = ['name' => $estado->nome, 'pipeline_id' => $pipelineId];

        if ($statusId !== null) {
            $card['status_id'] = $statusId;
        }

        $valor = CamposDoLead::valorNumerico($estado->campo('valor_total'))
            ?? CamposDoLead::valorNumerico($estado->campo('valor_proposta'));

        if ($valor !== null) {
            $card['price'] = (int) round($valor);
        }

        if ($customFields !== []) {
            $card['custom_fields_values'] = $customFields;
        }

        $card['_embedded'] = [
            'contacts' => array_map(fn (int $id): array => ['id' => $id], $estado->contactIds),
        ];

        $estado->cardCriado = true;

        $observacoes = [];

        if ($estado->contactIds === []) {
            $observacoes[] = 'Lead sem contato vinculado: o card sairia sem contato.';
        }

        if ($vazios !== []) {
            $observacoes[] = 'Vazios no lead, não copiados: ' . DescritorDeRegra::lista($vazios, 'e') . '.';
        }

        if ($naoLidos !== []) {
            $observacoes[] = DescritorDeRegra::lista($naoLidos, 'e') . ': o painel ainda não lê esses campos; na execução real são copiados do lead.';
        }

        return new AcaoSimulada(
            tipo: 'criar_card_pipeline',
            descricao: "Criar card no {$nomePipeline} ("
                . ($statusId !== null ? 'etapa ' . $this->nomes->status($pipelineId, $statusId) : 'etapa de entrada')
                . '), com o mesmo contato'
                . ($copiados !== [] ? ', copiando ' . DescritorDeRegra::lista($copiados, 'e') : ''),
            metodo: 'POST',
            endpoint: '/api/v4/leads',
            payload: [$card],
            observacao: $observacoes !== [] ? implode(' ', $observacoes) : null,
        );
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function iniciarRegua(array $a, EstadoDoLead $estado): AcaoSimulada
    {
        $etapaNome = $this->nomes->status($estado->pipelineId, $estado->statusId);
        $planId = isset($a['followup_plan_id']) && is_numeric($a['followup_plan_id']) ? (int) $a['followup_plan_id'] : null;
        $observacao = null;

        if ($planId !== null) {
            $plan = FollowupPlan::query()->find($planId);

            if ($plan === null) {
                return AcaoSimulada::ignorada('iniciar_regua', 'Iniciar régua de follow-up', "Régua #{$planId} não encontrada.");
            }

            $descricao = "Iniciar a régua “{$plan->nome}”";
        } else {
            $plan = $this->reguaDaEtapa($estado);
            $descricao = $plan !== null
                ? "Iniciar a régua “{$plan->nome}” (ligada à etapa {$etapaNome})"
                : "Iniciar a régua de follow-up da etapa {$etapaNome}";

            if ($plan === null) {
                $observacao = 'Ainda não há régua cadastrada para esta etapa; quando houver, é ela que começa aqui.';
            }
        }

        if ($plan !== null && ! $plan->ativo) {
            $observacao = 'A régua está desativada: nada seria agendado até ativá-la.';
        }

        return new AcaoSimulada(
            tipo: 'iniciar_regua',
            descricao: $descricao,
            metodo: 'PAINEL',
            endpoint: 'Follow-up (Fase 2) · agendar régua',
            payload: [
                'acao' => 'iniciar_regua',
                'followup_plan_id' => $plan?->id,
                'kommo_lead_id' => $estado->id,
                'status_id' => $estado->statusId,
            ],
            observacao: $observacao,
        );
    }

    private function cancelarReguas(EstadoDoLead $estado, AutomationRule $regra): AcaoSimulada
    {
        $pendentes = FollowupRun::query()
            ->where('lead_id_kommo', $estado->id)
            ->where('status', FollowupRunStatus::Agendado)
            ->count();

        return new AcaoSimulada(
            tipo: 'cancelar_reguas',
            descricao: 'Cancelar os follow-ups pendentes do lead' . ($pendentes > 0 ? " ({$pendentes} agendados)" : ''),
            metodo: 'PAINEL',
            endpoint: 'Follow-up (Fase 2) · cancelar pendentes',
            payload: [
                'acao' => 'cancelar_followups_pendentes',
                'kommo_lead_id' => $estado->id,
                'motivo' => 'automação ' . $regra->rotulo(),
            ],
            observacao: $pendentes === 0 ? 'Hoje não há follow-up agendado para este lead; nada a cancelar.' : null,
        );
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function dispararSalesbot(array $a, EstadoDoLead $estado): AcaoSimulada
    {
        $bot = isset($a['bot_id']) && is_numeric($a['bot_id']) ? (int) $a['bot_id'] : null;

        return new AcaoSimulada(
            tipo: 'disparar_salesbot',
            descricao: $bot !== null
                ? "Disparar o Salesbot #{$bot} na conversa do lead"
                : 'Disparar o Salesbot do médico na conversa do lead',
            metodo: 'POST',
            endpoint: '/api/v2/salesbot/run',
            payload: [[
                'bot_id' => $bot ?? '{Salesbot configurado no médico}',
                'entity_id' => $estado->id,
                'entity_type' => 2,
            ]],
            observacao: 'entity_type 2 = lead, na API de Salesbot do Kommo.',
        );
    }

    private function criarRecebivel(EstadoDoLead $estado, AutomationRule $regra): AcaoSimulada
    {
        $valor = CamposDoLead::valorNumerico($estado->campo('valor_total'))
            ?? CamposDoLead::valorNumerico($estado->campo('valor_proposta'));

        return new AcaoSimulada(
            tipo: 'criar_recebivel',
            descricao: 'Gerar recebível' . ($valor !== null ? ' de ' . CamposDoLead::moeda($valor) : '')
                . " no Financeiro para {$estado->nome}",
            metodo: 'PAINEL',
            endpoint: 'Financeiro · contas a receber',
            payload: [
                'acao' => 'criar_recebivel',
                'kommo_lead_id' => $estado->id,
                'paciente' => $estado->nome,
                'valor' => $valor,
                'origem' => 'automação ' . $regra->rotulo(),
            ],
            observacao: $valor === null
                ? 'Lead sem Valor total nem Valor da proposta: o recebível nasceria sem valor, para a recepção completar.'
                : null,
        );
    }

    // --------------------------------------------------------------- apoio

    private function reguaDaEtapa(EstadoDoLead $estado): ?FollowupPlan
    {
        return FollowupPlan::query()
            ->with('doctor')
            ->where('gatilho', FollowupGatilho::Etapa)
            ->get()
            ->first(fn (FollowupPlan $plan): bool => (int) ($plan->gatilho_config['status_id'] ?? 0) === $estado->statusId
                && (int) $plan->doctor?->kommo_pipeline_id === $estado->pipelineId);
    }

    /**
     * "{fup}" vira o número da etapa de FUP atual; null se o lead não está em FUP.
     */
    private function resolverTag(string $tag, EstadoDoLead $estado): ?string
    {
        $tag = trim($tag);

        if (! str_contains($tag, '{fup}')) {
            return $tag;
        }

        $numero = match ($estado->etapa()) {
            EtapaCanonica::Fup1 => '1',
            EtapaCanonica::Fup2 => '2',
            EtapaCanonica::Fup3 => '3',
            default => null,
        };

        return $numero === null ? null : str_replace('{fup}', $numero, $tag);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadTags(EstadoDoLead $estado): array
    {
        return ['_embedded' => ['tags' => array_map(fn (string $t): array => ['name' => $t], $estado->tags)]];
    }

    /**
     * O field_id é resolvido pelo nome na execução real (CustomFieldMap);
     * no protótipo aparece como {Nome do campo}.
     *
     * @return array<string, mixed>
     */
    private function campoPayload(string $campo, string $valor): array
    {
        return [
            'field_id' => '{' . CamposDoLead::rotulo($campo) . '}',
            'values' => [['value' => $this->valorParaApi($campo, $valor)]],
        ];
    }

    private function valorParaApi(string $campo, string $valor): string|int|float
    {
        if ($campo === 'proxima_consulta') {
            try {
                return CarbonImmutable::createFromFormat('d/m/Y H:i', $valor, (string) config('painel.timezone'))?->getTimestamp() ?? $valor;
            } catch (\Throwable) {
                return $valor;
            }
        }

        if ($campo === 'valor_proposta') {
            return CamposDoLead::valorNumerico($valor) ?? $valor;
        }

        return $valor;
    }

    private function etapaLegivel(EstadoDoLead $estado): string
    {
        $nome = $this->nomes->status($estado->pipelineId, $estado->statusId);
        $etapa = $estado->etapa();

        if ($etapa === null) {
            return "{$nome} (sem etapa equivalente no mapa)";
        }

        return $etapa->getLabel() === $nome ? $nome : "{$etapa->getLabel()} ({$nome})";
    }

    private static function iguais(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        return EstadoDoLead::normalizar($a) === EstadoDoLead::normalizar($b);
    }
}
