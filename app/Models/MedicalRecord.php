<?php

namespace App\Models;

use App\Enums\TipoRegistroProntuario;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Registro do prontuário. Conteúdo e dados estruturados ficam cifrados.
 * Depois de assinado vira somente leitura (nem edição nem exclusão).
 *
 * @property TipoRegistroProntuario $tipo
 * @property ?array<string, mixed> $dados
 * @property ?array<int, array<string, mixed>> $anexos
 * @property ?\Illuminate\Support\Carbon $assinado_em
 */
class MedicalRecord extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_id',
        'user_id',
        'tipo',
        'titulo',
        'conteudo',
        'dados',
        'anexos',
        'assinado_em',
        'demo',
    ];

    /**
     * Ângulos padronizados do registro fotográfico.
     *
     * @var array<string, string>
     */
    public const ANGULOS = [
        'frontal' => 'Frontal',
        'perfil_direito' => 'Perfil direito',
        'perfil_esquerdo' => 'Perfil esquerdo',
        'obliqua_direita' => 'Oblíqua direita',
        'obliqua_esquerda' => 'Oblíqua esquerda',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoRegistroProntuario::class,
            'conteudo' => 'encrypted',
            'dados' => 'encrypted:array',
            'anexos' => 'array',
            'assinado_em' => 'datetime',
            'demo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Trava no model, além da policy: registro assinado não muda.
        static::updating(function (MedicalRecord $record): void {
            if ($record->getOriginal('assinado_em') !== null) {
                throw new DomainException('Registro assinado não pode ser alterado.');
            }
        });

        static::deleting(function (MedicalRecord $record): void {
            if ($record->getOriginal('assinado_em') !== null) {
                throw new DomainException('Registro assinado não pode ser excluído.');
            }
        });
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
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estaAssinado(): bool
    {
        return $this->assinado_em !== null;
    }

    /**
     * Uma linha para a linha do tempo.
     */
    public function resumo(): string
    {
        $dados = $this->dados ?? [];

        $texto = match ($this->tipo) {
            TipoRegistroProntuario::Anamnese => 'Queixa: ' . ($dados['queixa_principal'] ?? '—'),
            TipoRegistroProntuario::Prescricao => collect($dados['itens'] ?? [])->pluck('medicamento')->filter()->implode(', '),
            TipoRegistroProntuario::Exame => collect($dados['exames'] ?? [])->filter()->implode(', '),
            TipoRegistroProntuario::Atestado => ($dados['dias'] ?? '?') . ' dia(s) de afastamento' . (filled($dados['cid'] ?? null) ? ' · CID ' . $dados['cid'] : ''),
            TipoRegistroProntuario::Foto => count($this->anexos ?? []) . ' foto(s) · ' . collect($this->anexos ?? [])->pluck('angulo')->map(fn ($a) => self::ANGULOS[$a] ?? $a)->unique()->implode(', '),
            TipoRegistroProntuario::Evolucao => strip_tags((string) $this->conteudo),
        };

        return Str::limit(trim($texto), 110);
    }
}
