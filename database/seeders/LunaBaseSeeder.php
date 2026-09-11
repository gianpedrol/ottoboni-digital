<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\IaGateSetting;
use App\Models\IaPromptVersion;
use App\Models\IaTrigger;
use Illuminate\Database\Seeder;

/**
 * Carrega no painel o que a Luna usa HOJE no n8n, para a clínica revisar:
 *
 *  - prompt v1: os blocos PERSONA/BASE do nó CONFIG (Direct) e as instruções
 *    do nó "Duda Comentario" (comentários), divididos nos blocos editáveis.
 *    Entra como versão ativa SEM aceite de responsabilidade — é o que já
 *    roda, ninguém revisou ainda. O painel mostra isso em amarelo até alguém
 *    publicar a v2 com o aceite.
 *  - regras inegociáveis (IaGuardrailSeeder)
 *  - gatilho de comentário ("chique")
 *
 * Os cards vêm do Supabase: php artisan ia:importar-supabase luna
 *
 * Idempotente: se a Luna já tem alguma versão de prompt, não cria outra.
 *
 *   php artisan db:seed --class=LunaBaseSeeder --force
 */
class LunaBaseSeeder extends Seeder
{
    public const MOTIVO_V1 = 'v1 importada do fluxo atual do n8n (nós CONFIG e Duda Comentario), sem revisão humana';

    public function run(): void
    {
        $luna = Doctor::query()->where('agente', 'luna')->first();

        if ($luna === null) {
            $this->command?->error('A Luna não existe na tabela doctors. Rode php artisan db:seed primeiro.');

            return;
        }

        $this->call(IaGuardrailSeeder::class);
        $this->promptV1($luna);
        $this->gatilhos($luna);

        // Garante a linha de configuração (modo treinamento por padrão).
        IaGateSetting::paraAgente($luna->id);
    }

    private function promptV1(Doctor $luna): void
    {
        $jaTem = IaPromptVersion::query()->where('doctor_id', $luna->id)->exists();

        if ($jaTem) {
            $this->command?->line('Prompt: a Luna já tem versão cadastrada; nada a fazer.');

            return;
        }

        $blocos = self::blocosV1();

        IaPromptVersion::query()->create([
            'doctor_id' => $luna->id,
            'versao' => 1,
            'blocos' => $blocos,
            'ativo' => true,
            'autor_id' => null,
            'motivo' => self::MOTIVO_V1,
            'aceite_responsabilidade' => false,
            'aceite_texto' => null,
            'user_agent' => 'seeder',
        ]);

        $this->command?->info('Prompt: v1 da Luna criada com '.count($blocos).' blocos ('.array_sum(array_map('mb_strlen', $blocos)).' caracteres).');
    }

    /**
     * Lê os blocos de database/seeders/data/luna/prompt-v1/*.md.
     *
     * @return array<string, string>
     */
    public static function blocosV1(): array
    {
        $pasta = database_path('seeders/data/luna/prompt-v1');
        $blocos = [];

        foreach (array_keys(IaPromptVersion::BLOCOS) as $chave) {
            $arquivo = "{$pasta}/{$chave}.md";

            if (! is_file($arquivo)) {
                continue;
            }

            $texto = trim((string) file_get_contents($arquivo));

            if ($texto !== '') {
                $blocos[$chave] = $texto;
            }
        }

        return $blocos;
    }

    private function gatilhos(Doctor $luna): void
    {
        // GATILHOS_COMENTARIO do nó CONFIG Meta: só a palavra. Os textos de
        // resposta fixos continuam no fluxo até o painel assumir isso também.
        $gatilhos = [
            ['termo' => 'chique', 'tipo' => 'palavra_chave'],
        ];

        foreach ($gatilhos as $ordem => $g) {
            IaTrigger::query()->firstOrCreate(
                ['doctor_id' => $luna->id, 'termo' => $g['termo']],
                ['tipo' => $g['tipo'], 'ordem' => $ordem, 'ativo' => true],
            );
        }
    }
}
