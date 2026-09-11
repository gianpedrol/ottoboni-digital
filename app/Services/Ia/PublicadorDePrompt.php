<?php

namespace App\Services\Ia;

use App\Models\AuditLog;
use App\Models\IaPromptVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Publica uma versão nova das instruções da agente.
 *
 * Versão não é editada, é criada: cada publicação guarda quem fez, por quê,
 * quando e o aceite de responsabilidade. Rollback é republicar uma versão
 * antiga — o histórico nunca é reescrito.
 *
 * O aceite é exigido aqui, na camada de serviço. Se amanhã existir outra tela
 * ou um comando de console, a regra continua valendo.
 */
class PublicadorDePrompt
{
    public const TEXTO_ACEITE = 'Declaro que revisei estas instruções e assumo total responsabilidade pelo que a agente responder com elas.';

    /**
     * @param  array<string, string>  $blocos
     */
    public function publicar(
        int $doctorId,
        array $blocos,
        User $autor,
        ?string $motivo,
        bool $aceite,
        ?string $userAgent = null,
    ): IaPromptVersion {
        if (! $aceite) {
            throw new RuntimeException('É obrigatório aceitar a responsabilidade pela alteração das instruções.');
        }

        // Só os blocos previstos entram — nada de chave solta vinda do request.
        $blocos = array_intersect_key($blocos, IaPromptVersion::BLOCOS);
        $blocos = array_filter($blocos, fn ($v): bool => filled($v));

        if ($blocos === []) {
            throw new RuntimeException('Não é possível publicar instruções vazias.');
        }

        return DB::transaction(function () use ($doctorId, $blocos, $autor, $motivo, $userAgent): IaPromptVersion {
            $anterior = IaPromptVersion::query()
                ->where('doctor_id', $doctorId)
                ->where('ativo', true)
                ->first();

            $proxima = (int) IaPromptVersion::query()
                ->where('doctor_id', $doctorId)
                ->max('versao') + 1;

            IaPromptVersion::query()
                ->where('doctor_id', $doctorId)
                ->where('ativo', true)
                ->update(['ativo' => false]);

            /** @var IaPromptVersion $versao */
            $versao = IaPromptVersion::query()->create([
                'doctor_id' => $doctorId,
                'versao' => $proxima,
                'blocos' => $blocos,
                'ativo' => true,
                'autor_id' => $autor->id,
                'motivo' => $motivo,
                'aceite_responsabilidade' => true,
                'aceite_texto' => self::TEXTO_ACEITE,
                'user_agent' => $userAgent,
            ]);

            AuditLog::registrar('ia.publicou_prompt', [
                'doctor_id' => $doctorId,
                'versao' => $proxima,
                'versao_anterior' => $anterior?->versao,
                'motivo' => $motivo,
                'blocos_alterados' => $this->blocosAlterados($anterior->blocos ?? [], $blocos),
            ], $autor->id);

            return $versao;
        });
    }

    /**
     * Republica uma versão antiga como versão nova (o histórico fica intacto).
     */
    public function reverter(IaPromptVersion $versao, User $autor, bool $aceite): IaPromptVersion
    {
        return $this->publicar(
            $versao->doctor_id,
            $versao->blocos,
            $autor,
            "Rollback para a versão {$versao->versao}",
            $aceite,
        );
    }

    /**
     * @param  array<string, string>  $antes
     * @param  array<string, string>  $depois
     * @return array<int, string>
     */
    private function blocosAlterados(array $antes, array $depois): array
    {
        $alterados = [];

        foreach (array_keys(IaPromptVersion::BLOCOS) as $bloco) {
            if (($antes[$bloco] ?? null) !== ($depois[$bloco] ?? null)) {
                $alterados[] = $bloco;
            }
        }

        return $alterados;
    }
}
