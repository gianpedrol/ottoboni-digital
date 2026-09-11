<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\IaCard;
use App\Models\IaPromptVersion;
use App\Models\IaTrigger;
use App\Models\User;
use App\Support\SegredosDoPainel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Traz do Supabase o que passa a viver no painel: cards da base, gatilhos de
 * comentário e o prompt atual.
 *
 * Roda uma vez por agente, na virada. Idempotente: repetir não duplica
 * (cards são casados por pergunta, gatilhos por termo).
 *
 *   php artisan ia:importar-supabase luna --dry-run
 *   php artisan ia:importar-supabase luna
 *
 * Fonte: SUPABASE_URL/SUPABASE_KEY do .env ou, sem eles, o webhook "ia-cards"
 * do n8n (IA_CARDS_URL) — aí a chave do Supabase nunca sai do n8n.
 *
 * Memória de conversa e echo NÃO são importados de propósito: continuam no
 * Supabase, onde só o n8n mexe.
 */
class IaImportarSupabase extends Command
{
    protected $signature = 'ia:importar-supabase
        {agente : duda ou luna}
        {--dry-run : só mostra o que seria importado}
        {--cards-table= : nome da tabela de cards (padrão <agente>_cards)}
        {--triggers-table= : nome da tabela de gatilhos (padrão <agente>_gatilhos)}';

    protected $description = 'Importa cards, gatilhos e prompt do Supabase para o painel';

    public function handle(): int
    {
        $agente = (string) $this->argument('agente');
        $doctor = Doctor::query()->where('agente', $agente)->first();

        if ($doctor === null) {
            $this->error("Agente \"{$agente}\" não existe na tabela doctors.");

            return self::FAILURE;
        }

        $url = rtrim((string) config('painel.ia.supabase_url'), '/');
        $key = (string) config('painel.ia.supabase_key');
        $viaN8n = (string) config('painel.ia.cards_url');

        if (($url === '' || $key === '') && $viaN8n === '') {
            $this->error('Configure SUPABASE_URL e SUPABASE_KEY no .env (ou IA_CARDS_URL, para buscar pelo n8n) antes de importar.');

            return self::FAILURE;
        }

        $this->line(($url !== '' && $key !== '')
            ? 'Fonte: Supabase direto.'
            : "Fonte: n8n ({$viaN8n}) — a chave do Supabase fica só lá.");

        $seco = (bool) $this->option('dry-run');

        if ($seco) {
            $this->warn('Modo dry-run: nada será gravado.');
        }

        $cardsTable = (string) ($this->option('cards-table') ?: "{$agente}_cards");
        $triggersTable = (string) ($this->option('triggers-table') ?: "{$agente}_gatilhos");

        $this->importarCards($doctor, $url, $key, $cardsTable, $seco);
        $this->importarGatilhos($doctor, $url, $key, $triggersTable, $seco);
        $this->avisarSobrePrompt($doctor);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $filtros  filtros PostgREST (ex.: ['conta' => 'eq.luna'])
     * @return array<int, array<string, mixed>>
     */
    private function buscar(string $url, string $key, string $tabela, array $filtros = []): array
    {
        if ($url === '' || $key === '') {
            return $this->buscarViaN8n($tabela, $filtros);
        }

        $resposta = Http::withHeaders(['apikey' => $key, 'Authorization' => "Bearer {$key}"])
            ->timeout(30)
            ->get("{$url}/rest/v1/{$tabela}", $filtros + ['select' => '*', 'limit' => 1000, 'order' => 'id.asc']);

        if ($resposta->failed()) {
            throw new \RuntimeException("Supabase devolveu {$resposta->status()} para {$tabela}.");
        }

        return $resposta->json() ?? [];
    }

    /**
     * Sem chave do Supabase no servidor, o n8n busca por nós: webhook "ia-cards"
     * do workflow IA ENVIO APROVADO, assinado com o mesmo HMAC das outras rotas.
     *
     * @param  array<string, string>  $filtros
     * @return array<int, array<string, mixed>>
     */
    private function buscarViaN8n(string $tabela, array $filtros): array
    {
        $agente = str_replace('eq.', '', (string) ($filtros['conta'] ?? 'luna'));
        $corpo = json_encode(['agente' => $agente, 'tabela' => $tabela], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $assinatura = 'sha256='.hash_hmac('sha256', (string) $corpo, SegredosDoPainel::webhookIa());

        $resposta = Http::withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-Signature' => $assinatura])
            ->timeout(40)
            ->withBody((string) $corpo, 'application/json')
            ->post((string) config('painel.ia.cards_url'));

        if ($resposta->failed()) {
            throw new \RuntimeException("n8n devolveu {$resposta->status()} para {$tabela} (o workflow IA ENVIO APROVADO está ativo e com o segredo colado?).");
        }

        $json = $resposta->json();

        if (! is_array($json) || ($json['ok'] ?? false) !== true) {
            throw new \RuntimeException('n8n não conseguiu ler '.$tabela.': '.(string) ($json['erro'] ?? 'resposta inesperada'));
        }

        return is_array($json['linhas'] ?? null) ? $json['linhas'] : [];
    }

    /**
     * Cards que o fluxo do n8n deixa fora do prompt hoje (lista EXCLUIR nos
     * nós Normalize e Duda Comentario). Entram desativados para a clínica
     * decidir, em vez de sumirem na importação.
     */
    private const FORA_DO_PROMPT = ['local.endereco', 'local.pleno_online'];

    private function importarCards(Doctor $doctor, string $url, string $key, string $tabela, bool $seco): void
    {
        try {
            $linhas = $this->buscar($url, $key, $tabela, ['conta' => 'eq.'.$doctor->agente]);
        } catch (Throwable $e) {
            $this->warn("Cards ({$tabela}): {$e->getMessage()}");

            return;
        }

        $novos = 0;
        $existentes = 0;
        $desativados = 0;

        foreach ($linhas as $linha) {
            $card = $this->mapearCard($linha);

            if ($card === null) {
                continue;
            }

            $jaTem = IaCard::query()
                ->where('doctor_id', $doctor->id)
                ->when(
                    $card['codigo'] !== null,
                    fn ($q) => $q->where('codigo', $card['codigo']),
                    fn ($q) => $q->where('pergunta', $card['pergunta']),
                )
                ->exists();

            if ($jaTem) {
                $existentes++;

                continue;
            }

            $novos++;

            if (! $card['ativo']) {
                $desativados++;
            }

            if ($seco) {
                $this->line("  + [{$card['codigo']}] {$card['categoria']} ({$card['status']}".($card['ativo'] ? '' : ', desativado').')');

                continue;
            }

            IaCard::query()->create($card + ['doctor_id' => $doctor->id, 'origem' => 'base_inicial']);
        }

        $this->info("Cards: {$novos} novo(s), {$existentes} já existiam (de ".count($linhas).' no Supabase).');

        if ($desativados > 0) {
            $this->line("  {$desativados} entraram desativados (bloqueados no Supabase ou fora do prompt do n8n). Revise em Cards.");
        }
    }

    /**
     * luna_cards: id, conta, modulo, categoria, perguntas_equivalentes[],
     * resposta_curta, resposta_detalhada, status (validado|revisar|pendente),
     * bloqueado. Aceita também os nomes simples (pergunta/resposta) de outras bases.
     *
     * @param  array<string, mixed>  $linha
     * @return array<string, mixed>|null
     */
    private function mapearCard(array $linha): ?array
    {
        $codigo = filled($linha['id'] ?? null) && ! is_numeric($linha['id']) ? (string) $linha['id'] : null;

        $equivalentes = $linha['perguntas_equivalentes'] ?? null;

        if (is_string($equivalentes)) {
            $decodificado = json_decode($equivalentes, true);
            $equivalentes = is_array($decodificado) ? $decodificado : [$equivalentes];
        }

        $equivalentes = is_array($equivalentes)
            ? array_values(array_filter(array_map(fn ($p): string => trim((string) $p), $equivalentes), fn (string $p): bool => $p !== ''))
            : [];

        $pergunta = trim((string) ($linha['pergunta'] ?? $linha['titulo'] ?? $linha['question'] ?? ($equivalentes[0] ?? '')));
        $categoria = trim((string) ($linha['categoria'] ?? ''));

        if ($pergunta === '' && $categoria !== '') {
            $pergunta = $categoria;
        }

        $resposta = trim((string) ($linha['resposta_curta'] ?? $linha['resposta'] ?? $linha['conteudo'] ?? $linha['answer'] ?? ''));

        if ($pergunta === '' || $resposta === '') {
            return null;
        }

        $status = (string) ($linha['status'] ?? 'validado');

        if (! array_key_exists($status, IaCard::STATUS)) {
            // Qualquer status desconhecido é tratado como pendente: validação
            // é decisão da clínica, não da importação.
            $status = 'pendente';
        }

        $bloqueado = (bool) ($linha['bloqueado'] ?? false);
        $ativo = ! $bloqueado && (bool) ($linha['ativo'] ?? true);

        if ($codigo !== null && in_array($codigo, self::FORA_DO_PROMPT, true)) {
            $ativo = false;
        }

        return [
            'codigo' => $codigo,
            'modulo' => filled($linha['modulo'] ?? null) ? (string) $linha['modulo'] : null,
            'categoria' => $categoria !== '' ? $categoria : null,
            'pergunta' => $pergunta,
            'perguntas_equivalentes' => $equivalentes !== [] ? $equivalentes : null,
            'resposta' => $resposta,
            'resposta_detalhada' => filled($linha['resposta_detalhada'] ?? null) ? trim((string) $linha['resposta_detalhada']) : null,
            'tags' => is_array($linha['tags'] ?? null) ? $linha['tags'] : null,
            'status' => $status,
            'ativo' => $ativo,
        ];
    }

    private function importarGatilhos(Doctor $doctor, string $url, string $key, string $tabela, bool $seco): void
    {
        try {
            $linhas = $this->buscar($url, $key, $tabela);
        } catch (Throwable $e) {
            $this->warn("Gatilhos ({$tabela}): {$e->getMessage()}");

            return;
        }

        $novos = 0;

        foreach ($linhas as $i => $linha) {
            $termo = trim((string) ($linha['termo'] ?? $linha['gatilho'] ?? $linha['palavra'] ?? ''));

            if ($termo === '') {
                continue;
            }

            if (IaTrigger::query()->where('doctor_id', $doctor->id)->where('termo', $termo)->exists()) {
                continue;
            }

            $novos++;

            if ($seco) {
                continue;
            }

            IaTrigger::query()->create([
                'doctor_id' => $doctor->id,
                'termo' => $termo,
                'tipo' => (string) ($linha['tipo'] ?? 'palavra_chave'),
                'ordem' => (int) ($linha['ordem'] ?? $i),
                'ativo' => (bool) ($linha['ativo'] ?? true),
            ]);
        }

        $this->info("Gatilhos: {$novos} novo(s) (de ".count($linhas).' no Supabase).');
    }

    /**
     * O prompt não é importado automaticamente: ele vive hoje em blocos de
     * texto dentro do nó CONFIG do n8n, não numa tabela. Copiar e colar bloco
     * por bloco na tela Prompts e regras é rápido e força alguém a revisar o
     * que está indo para a versão 1 — que é justamente o ponto do painel.
     */
    private function avisarSobrePrompt(Doctor $doctor): void
    {
        $tem = IaPromptVersion::query()->where('doctor_id', $doctor->id)->where('ativo', true)->exists();

        if ($tem) {
            $this->line('Prompt: já existe versão publicada.');

            return;
        }

        $this->newLine();
        $this->warn('Prompt: nenhuma versão publicada para esta agente.');

        if ($doctor->agente === 'luna') {
            $this->line('  Rode php artisan db:seed --class=LunaBaseSeeder --force para carregar a v1');
            $this->line('  (o prompt que está hoje no nó CONFIG do n8n), e revise em /ia/prompts-e-regras.');
        } else {
            $this->line('  Abra /ia/prompts-e-regras e cole os blocos do nó CONFIG do n8n');
            $this->line('  (PERSONA, ESCRITA, BASE, MODOS, PROCEDIMENTOS, ATENCAO, HANDOFF_MSG).');
        }

        $this->line('  Enquanto não houver versão publicada, a agente escala tudo para a equipe.');

        if (User::query()->count() === 0) {
            $this->warn('  Nenhum usuário cadastrado — crie um antes para poder publicar.');
        }
    }
}
