<?php

namespace App\Models;

use App\Enums\IaGateModo;
use App\Enums\IaIntent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuração do portão, por agente.
 *
 * @property IaGateModo $modo
 * @property array<int, string> $intents_sempre_revisa
 */
class IaGateSetting extends Model
{
    /**
     * Assuntos que NUNCA são liberados por acurácia, em nenhum modo.
     * "nao_sei" é o mais importante: se ela não sabe, quem responde é a equipe,
     * e a resposta vira card na base.
     */
    public const SEMPRE_REVISA_PADRAO = [
        IaIntent::NaoSei->value,
        IaIntent::Outro->value,
        IaIntent::Indefinido->value,
    ];

    public const DM_ESPERA_PADRAO = 'Oi! Vi sua mensagem 💛 Já estou verificando isso com a equipe e te respondo em seguida.';

    protected $fillable = [
        'doctor_id', 'modo', 'limiar_acuracia', 'kill_switch_acuracia',
        'min_amostras', 'janela', 'intents_sempre_revisa', 'timeout_min',
        'dm_espera_ativa', 'dm_espera_texto',
        'notif_emails', 'notif_push', 'atualizado_por',
    ];

    /**
     * "modelo" fica FORA do $fillable de propósito: assim nenhum update em
     * massa (nem um POST forjado no painel) troca o modelo da agente. Para
     * mudar de verdade existe o comando `ia:modelo`, que registra quem mudou.
     *
     * Cuidado ao editar: em Eloquent o $fillable vence o $guarded, então
     * declarar o campo nos dois lugares NÃO protege nada.
     */
    public function trocarModelo(string $modelo): void
    {
        $this->forceFill(['modelo' => $modelo])->save();
    }

    protected function casts(): array
    {
        return [
            'modo' => IaGateModo::class,
            'limiar_acuracia' => 'float',
            'kill_switch_acuracia' => 'float',
            'min_amostras' => 'integer',
            'janela' => 'integer',
            'timeout_min' => 'integer',
            'intents_sempre_revisa' => 'array',
            'notif_emails' => 'array',
            'dm_espera_ativa' => 'boolean',
            'notif_push' => 'boolean',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public static function paraAgente(int $doctorId): self
    {
        /** @var self $setting */
        $setting = static::query()->firstOrCreate(
            ['doctor_id' => $doctorId],
            [
                'intents_sempre_revisa' => self::SEMPRE_REVISA_PADRAO,
                'dm_espera_texto' => self::DM_ESPERA_PADRAO,
            ]
        );

        return $setting;
    }

    /** @return array<int, string> */
    public function sempreRevisa(): array
    {
        return $this->intents_sempre_revisa ?: self::SEMPRE_REVISA_PADRAO;
    }
}
