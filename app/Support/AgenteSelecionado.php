<?php

namespace App\Support;

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\User;

/**
 * Qual agente (Duda ou Luna) o usuário está treinando, sempre cruzado com o
 * escopo dele — a restrição vai no filtro da consulta, nunca só na view.
 */
class AgenteSelecionado
{
    /** @return array<int, string>  doctor_id => rótulo */
    public static function options(User $user): array
    {
        return Doctor::query()
            ->where('ativo', true)
            ->whereIn('id', self::permitidos($user))
            ->get()
            // A agente em treinamento primeiro na lista.
            ->sortBy(fn (Doctor $d): int => $d->agente === self::AGENTE_PADRAO ? 0 : 1)
            ->mapWithKeys(fn (Doctor $d): array => [
                $d->id => ucfirst($d->agente).' — '.$d->nome,
            ])
            ->all();
    }

    /**
     * Ids de médicos que este usuário pode treinar.
     *
     * @return array<int, int>
     */
    public static function permitidos(User $user): array
    {
        $query = Doctor::query()->where('ativo', true);

        if ($user->role === UserRole::Recepcao && $user->doctor_scope === DoctorScope::Eduardo) {
            $query->where('agente', 'duda');
        }

        if ($user->role === UserRole::Recepcao && $user->doctor_scope === DoctorScope::Vanessa) {
            $query->where('agente', 'luna');
        }

        return $query->pluck('id')->map(intval(...))->all();
    }

    /**
     * Agente que aparece selecionado ao abrir as telas: a Luna, que é quem
     * está em treinamento. A Duda já está validada e fica de fora do foco.
     */
    public const AGENTE_PADRAO = 'luna';

    /**
     * Resolve o agente escolhido na tela. Pedido fora do escopo (URL
     * manipulada) cai no padrão permitido — nunca amplia o acesso.
     */
    public static function resolver(User $user, int|string|null $doctorId): ?int
    {
        $permitidos = self::permitidos($user);

        if ($permitidos === []) {
            return null;
        }

        $id = (int) $doctorId;

        if (in_array($id, $permitidos, true)) {
            return $id;
        }

        $padrao = Doctor::query()
            ->where('agente', self::AGENTE_PADRAO)
            ->whereIn('id', $permitidos)
            ->value('id');

        return $padrao !== null ? (int) $padrao : $permitidos[0];
    }
}
