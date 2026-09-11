<?php

namespace App\Filament\Pages;

use App\Enums\EtapaCanonica;
use App\Models\User;
use App\Repositories\LeadRepository;
use App\Services\Automacoes\NomesDoKommo;
use App\Services\Kommo\DTO\PipelineData;
use App\Services\Kommo\KommoException;
use App\Support\MapaDeEtapas;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Etapas canônicas × pipelines mapeados, com o nome real no Kommo.
 * Somente leitura enquanto o mapa mora em config/etapas.php.
 */
class MapaEtapas extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Automações';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Mapa de etapas';

    protected static ?string $slug = 'automacoes/mapa-de-etapas';

    protected string $view = 'filament.pages.mapa-etapas';

    /** @var array<int, array{id: int, nome: string}> */
    public array $pipelines = [];

    /** @var array<int, array{etapa: string, cor: string, celulas: array<int, array<int, array{id: int, nome: ?string}>>}> */
    public array $linhas = [];

    public bool $comNomes = false;

    public ?string $aviso = null;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && ($user->isAdmin() || $user->isGestor());
    }

    public function mount(): void
    {
        /** @var Collection<int, PipelineData>|null $doKommo */
        $doKommo = null;

        try {
            $doKommo = app(LeadRepository::class)->pipelines()->keyBy('id');
            $this->comNomes = true;
        } catch (KommoException $e) {
            $this->aviso = "Não foi possível ler as etapas no Kommo agora ({$e->getMessage()}). A tabela mostra só os IDs do mapa.";
        }

        $referencia = new NomesDoKommo;
        $ausentes = [];

        foreach (MapaDeEtapas::pipelinesMapeados() as $id) {
            $pipeline = $doKommo?->get($id);

            if ($doKommo !== null && $pipeline === null) {
                $ausentes[] = $referencia->pipeline($id);
            }

            $this->pipelines[] = ['id' => $id, 'nome' => $pipeline !== null ? $pipeline->name : $referencia->pipeline($id)];
        }

        if ($ausentes !== []) {
            $this->aviso = 'O Kommo não devolveu ' . implode(' nem ', $ausentes) . '. Confira se o pipeline foi renomeado ou apagado.';
        }

        foreach (EtapaCanonica::cases() as $etapa) {
            $celulas = [];

            foreach ($this->pipelines as $p) {
                $celulas[$p['id']] = array_map(
                    fn (int $statusId): array => [
                        'id' => $statusId,
                        'nome' => $doKommo?->get($p['id'])?->statusName($statusId),
                    ],
                    MapaDeEtapas::statusIds($p['id'], $etapa),
                );
            }

            $this->linhas[] = [
                'etapa' => $etapa->getLabel(),
                'cor' => $etapa->getColor(),
                'celulas' => $celulas,
            ];
        }
    }
}
