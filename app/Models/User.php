<?php

namespace App\Models;

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;

/**
 * @property UserRole $role
 * @property DoctorScope $doctor_scope
 * @property ?string $app_authentication_secret
 * @property ?array<string> $app_authentication_recovery_codes
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
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
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'doctor_scope' => DoctorScope::class,
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return array<string>|null
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  array<string>|null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
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
        if ($this->role === UserRole::Recepcao && $this->doctor_scope === DoctorScope::Eduardo) {
            return [(int) config('kommo.pipelines.duda')];
        }

        if ($this->role === UserRole::Recepcao && $this->doctor_scope === DoctorScope::Vanessa) {
            return [(int) config('kommo.pipelines.luna')];
        }

        return array_map(intval(...), array_values(config('kommo.pipelines')));
    }
}
