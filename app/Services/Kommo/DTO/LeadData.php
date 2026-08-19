<?php

namespace App\Services\Kommo\DTO;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Lead do Kommo já com os campos personalizados das agentes resolvidos.
 * Nenhuma view ou relatório mexe em array cru da API.
 */
final readonly class LeadData
{
    public function __construct(
        public int $id,
        public string $name,
        public int $pipelineId,
        public int $statusId,
        public ?int $responsibleUserId,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
        public ?CarbonImmutable $closedAt,
        public int $price,
        public ?string $origem,
        public ?string $temperatura,
        public ?string $procedimento,
        public ?string $urgencia,
        public ?float $score,
        public ?string $instagram,
        /** @var array<int> */
        public array $contactIds,
        public ?string $lossReason,
    ) {}

    /**
     * @param  array<string, mixed>  $raw  lead cru da API
     * @param  array<string, int|null>  $fieldIds  chave de config => id do campo
     */
    public static function fromApi(array $raw, array $fieldIds): self
    {
        $cf = self::indexCustomFields($raw['custom_fields_values'] ?? null);

        $value = function (string $key) use ($cf, $fieldIds): ?string {
            $id = $fieldIds[$key] ?? null;

            return $id !== null ? ($cf[$id] ?? null) : null;
        };

        $score = $value('score');

        $contactIds = array_map(
            fn (array $c): int => (int) $c['id'],
            $raw['_embedded']['contacts'] ?? [],
        );

        $lossReason = $raw['_embedded']['loss_reason'][0]['name'] ?? null;

        return new self(
            id: (int) $raw['id'],
            name: (string) ($raw['name'] ?? ''),
            pipelineId: (int) ($raw['pipeline_id'] ?? 0),
            statusId: (int) ($raw['status_id'] ?? 0),
            responsibleUserId: isset($raw['responsible_user_id']) ? (int) $raw['responsible_user_id'] : null,
            createdAt: CarbonImmutable::createFromTimestampUTC((int) $raw['created_at']),
            updatedAt: isset($raw['updated_at']) ? CarbonImmutable::createFromTimestampUTC((int) $raw['updated_at']) : null,
            closedAt: isset($raw['closed_at']) && $raw['closed_at'] ? CarbonImmutable::createFromTimestampUTC((int) $raw['closed_at']) : null,
            price: (int) ($raw['price'] ?? 0),
            origem: $value('origem'),
            temperatura: $value('temperatura'),
            procedimento: $value('procedimento'),
            urgencia: $value('urgencia'),
            score: is_numeric($score) ? (float) $score : null,
            instagram: $value('instagram'),
            contactIds: $contactIds,
            lossReason: $lossReason,
        );
    }

    /**
     * Leads criados pelas agentes seguem o padrão de nome "IG @usuario".
     */
    public function geradoPelaAgente(): bool
    {
        return Str::startsWith(Str::lower($this->name), 'ig @');
    }

    public function instagramHandle(): ?string
    {
        if ($this->instagram !== null) {
            return Str::start(ltrim($this->instagram, '@'), '@');
        }

        if ($this->geradoPelaAgente()) {
            return '@' . ltrim(Str::after($this->name, '@'), '@');
        }

        return null;
    }

    public function ganho(): bool
    {
        return $this->statusId === (int) config('kommo.status_ganho');
    }

    public function perdido(): bool
    {
        return $this->statusId === (int) config('kommo.status_perdido');
    }

    public function kommoUrl(): string
    {
        return 'https://' . config('kommo.subdomain') . '.kommo.com/leads/detail/' . $this->id;
    }

    /**
     * Linha para a tabela do Filament (dados customizados usam arrays).
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'instagram' => $this->instagramHandle(),
            'pipeline_id' => $this->pipelineId,
            'status_id' => $this->statusId,
            'responsible_user_id' => $this->responsibleUserId,
            'origem' => $this->origem,
            'temperatura' => $this->temperatura,
            'procedimento' => $this->procedimento,
            'urgencia' => $this->urgencia,
            'score' => $this->score,
            'price' => $this->price,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'gerado_pela_agente' => $this->geradoPelaAgente(),
            'kommo_url' => $this->kommoUrl(),
        ];
    }

    /**
     * Indexa custom_fields_values como field_id => primeiro valor (string).
     *
     * @param  array<int, array<string, mixed>>|null  $values
     * @return array<int, string>
     */
    private static function indexCustomFields(?array $values): array
    {
        $out = [];

        foreach ($values ?? [] as $field) {
            $first = $field['values'][0]['value'] ?? null;

            if ($first !== null && $first !== '') {
                $out[(int) $field['field_id']] = (string) $first;
            }
        }

        return $out;
    }
}
