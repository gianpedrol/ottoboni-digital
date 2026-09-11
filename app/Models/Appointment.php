<?php

namespace App\Models;

use App\Enums\StatusAgendamento;
use App\Enums\TipoAgendamento;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * inicio/fim ficam em UTC (fuso da aplicação); a tela sempre converte para
 * config('painel.timezone').
 *
 * @property StatusAgendamento $status
 * @property TipoAgendamento $tipo
 * @property \Illuminate\Support\Carbon $inicio
 * @property \Illuminate\Support\Carbon $fim
 */
class Appointment extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'service_id',
        'tipo',
        'inicio',
        'fim',
        'status',
        'sala',
        'observacoes',
        'kommo_lead_id',
        'demo',
    ];

    protected $attributes = [
        'status' => 'agendado',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAgendamento::class,
            'status' => StatusAgendamento::class,
            'inicio' => 'datetime',
            'fim' => 'datetime',
            'kommo_lead_id' => 'integer',
            'demo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<MedicalRecord, $this>
     */
    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * Recepção só enxerga a agenda dos médicos do seu escopo. Filtro na
     * consulta, nunca só na view.
     *
     * @param  Builder<Appointment>  $query
     */
    public function scopeVisivelPara(Builder $query, User $user): void
    {
        self::restringirAoUsuario($query, $user);
    }

    /**
     * Mesmo filtro do scope, para quem recebe um Builder genérico (resource).
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function restringirAoUsuario(Builder $query, User $user): Builder
    {
        return $query->whereIn(
            'doctor_id',
            Doctor::query()->select('id')->whereIn('kommo_pipeline_id', $user->allowedPipelineIds()),
        );
    }

    /**
     * Agendamentos que ocupam o horário do médico.
     *
     * @param  Builder<Appointment>  $query
     */
    public function scopeOcupandoHorario(Builder $query): void
    {
        $query->whereNotIn('status', array_map(
            fn (StatusAgendamento $s): string => $s->value,
            StatusAgendamento::liberamHorario(),
        ));
    }

    /**
     * Intervalo [inicio, fim) que cruza com o informado.
     *
     * @param  Builder<Appointment>  $query
     */
    public function scopeSobrepondo(Builder $query, CarbonInterface $inicio, CarbonInterface $fim): void
    {
        $query->where('inicio', '<', self::paraBanco($fim))
            ->where('fim', '>', self::paraBanco($inicio));
    }

    /**
     * @param  Builder<Appointment>  $query
     */
    public function scopeEntre(Builder $query, CarbonInterface $de, CarbonInterface $ate): void
    {
        $query->where('inicio', '>=', self::paraBanco($de))
            ->where('inicio', '<', self::paraBanco($ate));
    }

    public static function fuso(): string
    {
        return (string) config('painel.timezone');
    }

    /**
     * O banco guarda no fuso da aplicação; o grammar do Laravel não converte
     * Carbon de outro fuso sozinho.
     */
    public static function paraBanco(CarbonInterface $data): CarbonImmutable
    {
        return CarbonImmutable::instance($data)->setTimezone((string) config('app.timezone'));
    }

    public function inicioLocal(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->inicio)->setTimezone(self::fuso());
    }

    public function fimLocal(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->fim)->setTimezone(self::fuso());
    }

    public function duracaoMin(): int
    {
        return (int) $this->inicio->diffInMinutes($this->fim, absolute: true);
    }

    public function faixaHorario(): string
    {
        return $this->inicioLocal()->format('H:i') . '–' . $this->fimLocal()->format('H:i');
    }
}
