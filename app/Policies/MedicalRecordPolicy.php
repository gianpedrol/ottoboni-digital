<?php

namespace App\Policies;

use App\Models\MedicalRecord;
use App\Models\User;

/**
 * Prontuário: só admin e gestor por enquanto. No produto final o acesso é
 * do médico responsável (ainda não existe papel de médico no painel).
 */
class MedicalRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->podeVerProntuario($user);
    }

    public function view(User $user, MedicalRecord $record): bool
    {
        return $this->podeVerProntuario($user);
    }

    public function create(User $user): bool
    {
        return $this->podeVerProntuario($user);
    }

    public function update(User $user, MedicalRecord $record): bool
    {
        return $this->podeVerProntuario($user) && ! $record->estaAssinado();
    }

    public function delete(User $user, MedicalRecord $record): bool
    {
        return $this->podeVerProntuario($user) && ! $record->estaAssinado();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function assinar(User $user, MedicalRecord $record): bool
    {
        return $this->podeVerProntuario($user) && ! $record->estaAssinado();
    }

    private function podeVerProntuario(User $user): bool
    {
        return $user->isAdmin() || $user->isGestor();
    }
}
