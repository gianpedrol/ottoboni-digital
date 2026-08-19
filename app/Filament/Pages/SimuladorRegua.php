<?php

namespace App\Filament\Pages;

use App\Models\FollowupPlan;
use App\Models\User;
use App\Repositories\LeadFilters;
use App\Repositories\LeadRepository;
use App\Services\Followup\SimuladorDeRegua;
use App\Services\Kommo\KommoException;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Simulador obrigatório da seção 7.4: escolhe um lead real e vê a régua
 * inteira renderizada com datas e textos, SEM enviar nada.
 *
 * @property-read Schema $form
 */
class SimuladorRegua extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Follow-up';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Simulador de régua';

    protected string $view = 'filament.pages.simulador-regua';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $simulacao = null;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && ($user->isAdmin() || $user->isGestor());
    }

    public function mount(): void
    {
        $this->form->fill([
            'plan_id' => request()->integer('plan') ?: null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('plan_id')
                    ->label('Régua')
                    ->options(FollowupPlan::query()->with('doctor')->get()->mapWithKeys(
                        fn (FollowupPlan $p): array => [$p->id => "{$p->nome} ({$p->doctor->nome})"],
                    ))
                    ->required()
                    ->live(),
                Select::make('lead_id')
                    ->label('Lead real (busque pelo nome ou @)')
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $search, $get): array => $this->buscarLeads($search, $get('plan_id')))
                    ->helperText('A busca cobre os últimos 60 dias do funil do médico da régua. Nada é enviado ao lead.'),
            ])
            ->columns(2);
    }

    public function simular(): void
    {
        $state = $this->form->getState();

        $plan = FollowupPlan::query()->findOrFail($state['plan_id']);

        try {
            $this->simulacao = app(SimuladorDeRegua::class)->simular($plan, (int) $state['lead_id']);
        } catch (KommoException $e) {
            Notification::make()
                ->danger()
                ->title('Falha ao consultar o Kommo')
                ->body($e->getMessage())
                ->send();

            return;
        }

        $this->simulacao['regua'] = $plan->nome;
    }

    /**
     * @return array<int, string>
     */
    private function buscarLeads(string $search, mixed $planId): array
    {
        if (blank($planId) || mb_strlen($search) < 2) {
            return [];
        }

        $plan = FollowupPlan::query()->with('doctor')->find($planId);

        if ($plan === null) {
            return [];
        }

        $tz = config('painel.timezone');

        $filters = new LeadFilters(
            pipelineIds: [(int) $plan->doctor->kommo_pipeline_id],
            from: CarbonImmutable::now($tz)->subDays(60)->startOfDay()->utc(),
            to: CarbonImmutable::now($tz)->endOfDay()->utc(),
            search: $search,
        );

        try {
            return app(LeadRepository::class)->leads($filters)
                ->take(20)
                ->mapWithKeys(fn ($lead): array => [
                    $lead->id => $lead->name . ($lead->instagramHandle() ? " ({$lead->instagramHandle()})" : ''),
                ])
                ->toArray();
        } catch (KommoException) {
            return [];
        }
    }
}
