<?php

namespace App\Support;

use App\Models\Doctor;
use App\Models\User;

/**
 * Traduz a escolha "Duda / Luna / ambos" das telas em pipeline_ids do Kommo,
 * SEMPRE cruzando com o escopo do usuário — a restrição vai no filtro da
 * consulta, nunca só na view.
 */
class SelecaoMedico
{
    /**
     * Opções do seletor de médico, já limitadas ao escopo do usuário.
     *
     * @return array<string, string>  agente => rótulo
     */
    public static function options(User $user): array
    {
        $permitidos = $user->allowedPipelineIds();

        $options = Doctor::query()
            ->where('ativo', true)
            ->whereIn('kommo_pipeline_id', $permitidos)
            ->orderBy('id')
            ->pluck('nome', 'agente')
            ->map(fn (string $nome, string $agente): string => $nome . ' (' . ucfirst($agente) . ')')
            ->toArray();

        if (count($options) > 1) {
            $options = ['ambos' => 'Os dois médicos'] + $options;
        }

        return $options;
    }

    /**
     * @return array<int>
     */
    public static function pipelineIds(User $user, ?string $agente): array
    {
        $permitidos = $user->allowedPipelineIds();

        if (blank($agente) || $agente === 'ambos') {
            return $permitidos;
        }

        $pipeline = Doctor::query()->where('agente', $agente)->value('kommo_pipeline_id');

        if ($pipeline === null || ! in_array((int) $pipeline, $permitidos, true)) {
            // Pediu um médico fora do escopo (ex.: URL manipulada) — devolve
            // só o que pode ver, nunca amplia.
            return $permitidos;
        }

        return [(int) $pipeline];
    }
}
