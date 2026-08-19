<?php

namespace App\Models;

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'doctor_scope',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'doctor_scope' => DoctorScope::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isGestor(): bool
    {
        return $this->role === UserRole::Gestor;
    }

    /**
     * Pipelines do Kommo que este usuário pode enxergar.
     * O escopo é aplicado NO FILTRO da consulta, nunca só na view.
     *
     * @return array<int>
     */
    public function allowedPipelineIds(): array
    {
        if ($this->role !== UserRole::Recepcao || $this->doctor_scope === DoctorScope::Ambos) {
            return array_values(config('kommo.pipelines'));
        }

        return match ($this->doctor_scope) {
            DoctorScope::Eduardo => [config('kommo.pipelines.duda')],
            DoctorScope::Vanessa => [config('kommo.pipelines.luna')],
            default => array_values(config('kommo.pipelines')),
        };
    }
}
