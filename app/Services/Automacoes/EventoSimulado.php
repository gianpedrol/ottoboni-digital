<?php

namespace App\Services\Automacoes;

use App\Enums\EtapaCanonica;
use Carbon\CarbonImmutable;

/**
 * O que "aconteceu no Kommo" numa simulação — o equivalente ao corpo do
 * webhook (status_lead, update_lead, add_lead) no motor real.
 *
 * geradoPelaIntegracao marca o evento que o próprio painel provocou ao
 * escrever no Kommo; o motor ignora esse evento (anti-loop).
 */
final readonly class EventoSimulado
{
    /** @var array<string, string> */
    public const TIPOS = [
        'etapa' => 'Lead entrou numa etapa',
        'campo_alterado' => 'Campo do lead mudou',
        'tag_adicionada' => 'Tag adicionada',
        'inatividade' => 'Lead parado há X horas',
        'lead_criado' => 'Lead criado',
    ];

    public function __construct(
        public string $tipo,
        public ?string $etapa = null,
        public ?string $campo = null,
        public ?string $valor = null,
        public ?string $tag = null,
        public ?int $horas = null,
        public bool $geradoPelaIntegracao = false,
    ) {}

    public static function entrouNaEtapa(EtapaCanonica|string $etapa): self
    {
        return new self('etapa', etapa: $etapa instanceof EtapaCanonica ? $etapa->value : $etapa);
    }

    public static function campoAlterado(string $campo, ?string $valor): self
    {
        return new self('campo_alterado', campo: $campo, valor: $valor);
    }

    public static function tagAdicionada(string $tag): self
    {
        return new self('tag_adicionada', tag: $tag);
    }

    public static function leadParado(int $horas): self
    {
        return new self('inatividade', horas: $horas);
    }

    public static function leadCriado(): self
    {
        return new self('lead_criado');
    }

    public function daIntegracao(): self
    {
        return new self($this->tipo, $this->etapa, $this->campo, $this->valor, $this->tag, $this->horas, true);
    }

    /**
     * Evento que dispara o gatilho da regra — usado como sugestão no
     * simulador e para montar as execuções de demonstração.
     *
     * @param  array<string, mixed>  $gatilho
     */
    public static function sugeridoPara(array $gatilho): ?self
    {
        return match ($gatilho['tipo'] ?? null) {
            'etapa' => isset($gatilho['etapas'][0]) ? self::entrouNaEtapa((string) $gatilho['etapas'][0]) : null,
            'campo_alterado' => self::campoAlterado((string) ($gatilho['campo'] ?? ''), (string) ($gatilho['valor'] ?? '')),
            'campo_preenchido' => self::campoAlterado(
                (string) ($gatilho['campo'] ?? ''),
                self::valorDeExemplo((string) ($gatilho['campo'] ?? '')),
            ),
            'tag_adicionada' => self::tagAdicionada((string) ($gatilho['tag'] ?? '')),
            'inatividade' => self::leadParado((int) ($gatilho['horas'] ?? 48)),
            'lead_criado' => self::leadCriado(),
            default => null,
        };
    }

    public static function valorDeExemplo(string $campo): string
    {
        $tz = (string) config('painel.timezone');

        return match ($campo) {
            'proxima_consulta' => CarbonImmutable::now($tz)->addDays(3)->setTime(14, 0)->format('d/m/Y H:i'),
            'data_assinatura' => CarbonImmutable::now($tz)->format('d/m/Y'),
            'data_cirurgia' => CarbonImmutable::now($tz)->addDays(30)->format('d/m/Y'),
            'valor_proposta', 'valor_total' => '18500',
            default => CamposDoLead::OPCOES[$campo][0] ?? 'preenchido',
        };
    }

    public function descricao(): string
    {
        $texto = match ($this->tipo) {
            'etapa' => 'Lead entrou na etapa ' . (EtapaCanonica::tryFrom((string) $this->etapa)?->getLabel() ?? (string) $this->etapa),
            'campo_alterado' => blank($this->valor)
                ? 'Campo ' . CamposDoLead::rotulo((string) $this->campo) . ' foi apagado'
                : 'Campo ' . CamposDoLead::rotulo((string) $this->campo) . " mudou para “{$this->valor}”",
            'tag_adicionada' => "Tag {$this->tag} adicionada",
            'inatividade' => "Lead parado há {$this->horas} h",
            'lead_criado' => 'Lead criado',
            default => $this->tipo,
        };

        return $this->geradoPelaIntegracao ? "{$texto} (alteração feita pelo próprio painel)" : $texto;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'tipo' => $this->tipo,
            'descricao' => $this->descricao(),
            'etapa' => $this->etapa,
            'campo' => $this->campo,
            'valor' => $this->valor,
            'tag' => $this->tag,
            'horas' => $this->horas,
            'gerado_pela_integracao' => $this->geradoPelaIntegracao ?: null,
        ], fn ($v): bool => $v !== null);
    }
}
