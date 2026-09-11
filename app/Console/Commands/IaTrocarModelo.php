<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\IaGateSetting;
use Illuminate\Console\Command;

/**
 * Troca o modelo de uma agente.
 *
 * Existe como comando e não como campo no painel de propósito: mudar de
 * modelo altera custo e comportamento de todas as respostas de uma vez, é
 * decisão técnica, e assim a troca fica registrada com data e responsável.
 */
class IaTrocarModelo extends Command
{
    protected $signature = 'ia:modelo {agente : duda ou luna} {modelo? : ex. gpt-4.1}';

    protected $description = 'Mostra ou troca o modelo usado por uma agente de IA';

    public function handle(): int
    {
        $agente = (string) $this->argument('agente');
        $doctor = Doctor::query()->where('agente', $agente)->first();

        if ($doctor === null) {
            $this->error("Agente \"{$agente}\" não existe.");

            return self::FAILURE;
        }

        $cfg = IaGateSetting::paraAgente($doctor->id);
        $novo = $this->argument('modelo');

        if ($novo === null) {
            $this->info("{$doctor->nome} ({$agente}) usa: {$cfg->modelo}");

            return self::SUCCESS;
        }

        $antigo = $cfg->modelo;

        if (! $this->confirm("Trocar o modelo de {$agente} de \"{$antigo}\" para \"{$novo}\"?", false)) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        $cfg->trocarModelo((string) $novo);

        AuditLog::query()->create([
            'user_id' => null,
            'acao' => 'ia.trocou_modelo',
            'contexto' => ['agente' => $agente, 'de' => $antigo, 'para' => $novo, 'via' => 'console'],
            'created_at' => now(),
        ]);

        $this->info("Modelo de {$agente}: {$antigo} -> {$novo}");

        return self::SUCCESS;
    }
}
