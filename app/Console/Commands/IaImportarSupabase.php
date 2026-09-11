<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\IaCard;
use App\Models\IaPromptVersion;
use App\Models\IaTrigger;
use App\Models\User;
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

        if ($url === '' || $key === '') {
            $this->error('Configure SUPABASE_URL e SUPABASE_KEY no .env antes de importar.');

            return self::FAILURE;
        }

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
     * @return array<int, array<string, mixed>>
     */
    private function buscar(string $url, string $key, string $tabela): array
    {
        $resposta = Http::withHeaders(['apikey' => $key, 'Authorization' => "Bearer {$key}"])
            ->timeout(30)
            ->get("{$url}/rest/v1/{$tabela}", ['select' => '*', 'limit' => 1000]);

        if ($resposta->failed()) {
            throw new \RuntimeException("Supabase devolveu {$resposta->status()} para {$tabela}.");
        }

        return $resposta->json() ?? [];
    }

    private function importarCards(Doctor $doctor, string $url, string $key, string $tabela, bool $seco): void
    {
        try {
            $linhas = $this->buscar($url, $key, $tabela);
        } catch (Throwable $e) {
            $this->warn("Cards ({$tabela}): {$e->getMessage()}");

            return;
        }

        $novos = 0;
        $existentes = 0;

        foreach ($linhas as $linha) {
            // Nomes de coluna variam entre as bases; aceita os mais comuns.
            $pergunta = trim((string) ($linha['pergunta'] ?? $linha['titulo'] ?? $linha['question'] ?? ''));
            $resposta = trim((string) ($linha['resposta'] ?? $linha['conteudo'] ?? $linha['answer'] ?? ''));

            if ($pergunta === '' || $resposta === '') {
                continue;
            }

            $jaTem = IaCard::query()
                ->where('doctor_id', $doctor->id)
                ->where('pergunta', $pergunta)
                ->exists();

            if ($jaTem) {
                $existentes++;

                continue;
            }

            $novos++;

            if ($seco) {
                continue;
            }

            IaCard::query()->create([
                'doctor_id' => $doctor->id,
                'pergunta' => $pergunta,
                'resposta' => $resposta,
                'tags' => is_array($linha['tags'] ?? null) ? $linha['tags'] : null,
                // Card pendente no Supabase continua pendente aqui: validação
                // é decisão da clínica, não da importação.
                'status' => ($linha['status'] ?? 'validado') === 'validado' ? 'validado' : 'pendente',
                'origem' => 'base_inicial',
                'ativo' => (bool) ($linha['ativo'] ?? true),
            ]);
        }

        $this->info("Cards: {$novos} novo(s), {$existentes} já existiam (de " . count($linhas) . ' no Supabase).');
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

        $this->info("Gatilhos: {$novos} novo(s) (de " . count($linhas) . ' no Supabase).');
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
        $this->line('  Abra /ia/prompts-e-regras e cole os blocos do nó CONFIG do n8n');
        $this->line('  (PERSONA, ESCRITA, BASE, MODOS, PROCEDIMENTOS, ATENCAO, HANDOFF_MSG).');
        $this->line('  Enquanto não houver versão publicada, a agente escala tudo para a equipe.');

        if (User::query()->count() === 0) {
            $this->warn('  Nenhum usuário cadastrado — crie um antes para poder publicar.');
        }
    }
}
