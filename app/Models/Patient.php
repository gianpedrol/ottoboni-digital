<?php

namespace App\Models;

use App\Casts\CpfCifrado;
use App\Enums\KommoSyncStatus;
use App\Enums\UserRole;
use App\Support\Cpf;
use App\Support\Telefone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $telefone
 * @property ?string $cpf
 * @property KommoSyncStatus $kommo_sync_status
 * @property ?\Illuminate\Support\Carbon $data_nascimento
 */
class Patient extends Model
{
    protected $fillable = [
        'nome',
        'telefone',
        'email',
        'cpf',
        'data_nascimento',
        'sexo',
        'doctor_id',
        'procedimento_interesse',
        'origem',
        'indicacao',
        'observacoes',
        'kommo_contact_id',
        'kommo_lead_id',
        'kommo_sync_status',
        'kommo_sync_payload',
        'kommo_synced_at',
        'demo',
    ];

    /**
     * CPF fora de toArray()/JSON (LGPD): só aparece mascarado, ou no
     * formulário de edição, preenchido explicitamente.
     */
    protected $hidden = [
        'cpf',
    ];

    /**
     * @var array<string, string>
     */
    public const SEXOS = [
        'F' => 'Feminino',
        'M' => 'Masculino',
        'O' => 'Outro / prefere não informar',
    ];

    protected function casts(): array
    {
        return [
            'cpf' => CpfCifrado::class,
            'data_nascimento' => 'date',
            'kommo_contact_id' => 'integer',
            'kommo_lead_id' => 'integer',
            'kommo_sync_status' => KommoSyncStatus::class,
            'kommo_sync_payload' => 'array',
            'kommo_synced_at' => 'datetime',
            'demo' => 'boolean',
        ];
    }

    protected function telefone(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value,
            set: fn (?string $value): ?string => Telefone::normalizar($value) ?? $value,
        );
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<MedicalRecord, $this>
     */
    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * @return HasMany<Receivable, $this>
     */
    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    /**
     * Recepção só enxerga pacientes dos médicos do seu escopo.
     *
     * @param  Builder<Patient>  $query
     */
    public function scopeVisivelPara(Builder $query, User $user): void
    {
        self::restringirPara($query, $user);
    }

    /**
     * Mesma regra do scope, para quem recebe um Builder genérico
     * (ex.: Resource::getEloquentQuery()).
     */
    public static function restringirPara(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::Recepcao) {
            $query->whereHas('doctor', fn (Builder $q) => $q->whereIn('kommo_pipeline_id', $user->allowedPipelineIds()));
        }

        return $query;
    }

    /**
     * Status de agendamento que não contam como "próxima consulta".
     *
     * @return array<int, string>
     */
    public static function statusAgendaInativos(): array
    {
        return ['cancelado', 'remarcado', 'faltou', 'realizado'];
    }

    public function idade(): ?int
    {
        return $this->data_nascimento?->age;
    }

    public function cpfMascarado(): ?string
    {
        return Cpf::mascarar($this->cpf);
    }

    public function sexoLabel(): ?string
    {
        return self::SEXOS[$this->sexo] ?? null;
    }
}
