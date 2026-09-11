<?php

namespace App\Services\Automacoes;

use App\Enums\EtapaCanonica;
use App\Services\Kommo\DTO\LeadData;
use App\Support\MapaDeEtapas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Cópia de trabalho do lead durante uma avaliação. O evento simulado e
 * cada ação alteram esta cópia, para a ação seguinte partir do estado
 * que o Kommo teria depois da anterior. Nunca volta para o Kommo.
 */
final class EstadoDoLead
{
    /**
     * Tags incluídas por esta execução (a própria regra não as remove).
     *
     * @var array<int, string>
     */
    public array $tagsAdicionadas = [];

    public bool $cardCriado = false;

    /**
     * @param  array<int, string>  $tags
     * @param  array<string, ?string>  $campos
     * @param  array<int, int>  $contactIds
     */
    public function __construct(
        public int $id,
        public string $nome,
        public int $pipelineId,
        public int $statusId,
        public array $tags,
        public array $campos,
        public array $contactIds,
        public ?CarbonImmutable $atualizadoEm,
    ) {}

    /**
     * @param  array<string, ?string>  $camposExtras  campos que o LeadData não carrega
     */
    public static function deLead(LeadData $lead, array $camposExtras = []): self
    {
        return new self(
            id: $lead->id,
            nome: $lead->name,
            pipelineId: $lead->pipelineId,
            statusId: $lead->statusId,
            tags: array_values($lead->tags),
            campos: [...CamposDoLead::doLead($lead), ...$camposExtras],
            contactIds: $lead->contactIds,
            atualizadoEm: $lead->updatedAt,
        );
    }

    public function etapa(): ?EtapaCanonica
    {
        return MapaDeEtapas::canonica($this->pipelineId, $this->statusId);
    }

    public function campo(string $campo): ?string
    {
        $valor = $this->campos[$campo] ?? null;

        return $valor === null || trim($valor) === '' ? null : $valor;
    }

    public function temTag(string $tag): bool
    {
        return $this->indiceDaTag($tag) !== null;
    }

    public function adicionarTag(string $tag): void
    {
        if (! $this->temTag($tag)) {
            $this->tags[] = $tag;
        }
    }

    public function removerTag(string $tag): void
    {
        $i = $this->indiceDaTag($tag);

        if ($i !== null) {
            unset($this->tags[$i]);
            $this->tags = array_values($this->tags);
        }
    }

    /**
     * Tag comparada sem diferenciar maiúscula nem espaço sobrando — o Kommo
     * da clínica tem "botox" / "BOTOX" / "botox ".
     */
    public static function mesmaTag(string $a, string $b): bool
    {
        return self::normalizar($a) === self::normalizar($b);
    }

    public static function normalizar(string $valor): string
    {
        return Str::of($valor)->squish()->lower()->ascii()->toString();
    }

    private function indiceDaTag(string $tag): ?int
    {
        foreach ($this->tags as $i => $existente) {
            if (self::mesmaTag($existente, $tag)) {
                return $i;
            }
        }

        return null;
    }
}
