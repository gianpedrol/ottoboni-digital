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
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Doctor $d): array => [
                $d->id => ucfirst($d->agente) . ' — ' . $d->nome,
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
     * Resolve o agente escolhido na tela. Pedido fora do escopo (URL
     * manipulada) cai no primeiro permitido — nunca amplia o acesso.
     */
    public static function resolver(User $user, int|string|null $doctorId): ?int
    {
        $permitidos = self::permitidos($user);

        if ($permitidos === []) {
            return null;
        }

        $id = (int) $doctorId;

        return in_array($id, $permitidos, true) ? $id : $permitidos[0];
    }
}
