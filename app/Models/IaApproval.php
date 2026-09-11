<?php

namespace App\Models;

use App\Enums\IaApprovalStatus;
use App\Enums\IaCanal;
use App\Enums\IaGrauEdicao;
use App\Enums\IaIntent;
use App\Enums\IaMotivoFila;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um item da fila de aprovação: o que a agente quer responder e ainda
 * não saiu para o Instagram.
 *
 * @property IaApprovalStatus $status
 * @property IaIntent $intent
 * @property IaCanal $canal
 * @property IaMotivoFila $motivo_fila
 * @property ?IaGrauEdicao $grau_edicao
 */
class IaApproval extends Model
{
    protected $fillable = [
        'doctor_id', 'canal', 'intent', 'confianca', 'motivo_fila',
        'ig_id', 'ig_username', 'post_id', 'post_permalink', 'post_caption',
        'post_media_url', 'comment_id', 'comentario_texto', 'comentario_em',
        'mensagem_texto', 'historico',
        'rascunho_comentario', 'rascunho_dm', 'final_comentario', 'final_dm',
        'modelo', 'prompt_version_id', 'cards_usados', 'tokens_prompt', 'tokens_resposta',
        'status', 'grau_edicao', 'similaridade', 'score',
        'revisado_por', 'revisado_em', 'observacao_humano', 'responsabilidade_aceita',
        'dm_espera_enviada', 'notificado_em', 'expira_em', 'enviado_em', 'erro',
    ];

    protected function casts(): array
    {
        return [
            'canal' => IaCanal::class,
            'intent' => IaIntent::class,
            'motivo_fila' => IaMotivoFila::class,
            'status' => IaApprovalStatus::class,
            'grau_edicao' => IaGrauEdicao::class,
            'confianca' => 'float',
            'similaridade' => 'float',
            'score' => 'float',
            'historico' => 'array',
            'cards_usados' => 'array',
            'responsabilidade_aceita' => 'boolean',
            'dm_espera_enviada' => 'boolean',
            'comentario_em' => 'datetime',
            'revisado_em' => 'datetime',
            'notificado_em' => 'datetime',
            'expira_em' => 'datetime',
            'enviado_em' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(IaPromptVersion::class, 'prompt_version_id');
    }

    /** @param Builder<IaApproval> $query */
    public function scopePendentes(Builder $query): void
    {
        $query->where('status', IaApprovalStatus::Pendente);
    }

    /**
     * Itens que contam para a acurácia: só os que um humano revisou de fato.
     * "auto_enviado" fica fora — ninguém olhou, não pode inflar a nota.
     * "nao_sei" fica fora — por definição a agente não sabia.
     *
     * @param Builder<IaApproval> $query
     */
    public function scopeAvaliaveis(Builder $query): void
    {
        $query->whereIn('status', [IaApprovalStatus::Enviado, IaApprovalStatus::Rejeitado])
            ->where('intent', '!=', IaIntent::NaoSei)
            ->whereNotNull('score');
    }

    public function estaAtrasado(): bool
    {
        return $this->status === IaApprovalStatus::Pendente
            && $this->expira_em !== null
            && $this->expira_em->isPast();
    }

    public function textoDaPessoa(): string
    {
        return (string) ($this->comentario_texto ?? $this->mensagem_texto ?? '');
    }
}
