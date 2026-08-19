<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use App\Repositories\LeadRepository;
use App\Services\Kommo\CustomFieldMap;
use App\Services\Kommo\KommoException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class Configuracoes extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Administração';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Configurações';

    protected string $view = 'filament.pages.configuracoes';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<string, array{id: int, name: string}> */
    public array $mapaDeCampos = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $janela = Setting::get('janela_envio', config('painel.janela_envio'));

        $this->form->fill([
            'inicio' => $janela['inicio'],
            'fim' => $janela['fim'],
            'dias' => $janela['dias'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Janela de horário para envio de follow-up')
                    ->description('Fora desta janela nenhuma mensagem sai, mesmo agendada (o envio fica para o próximo horário permitido).')
                    ->schema([
                        TimePicker::make('inicio')
                            ->label('Início')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('fim')
                            ->label('Fim')
                            ->seconds(false)
                            ->required(),
                        CheckboxList::make('dias')
                            ->label('Dias permitidos')
                            ->options([
                                1 => 'Segunda',
                                2 => 'Terça',
                                3 => 'Quarta',
                                4 => 'Quinta',
                                5 => 'Sexta',
                                6 => 'Sábado',
                                7 => 'Domingo',
                            ])
                            ->columns(4)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public function salvar(): void
    {
        $state = $this->form->getState();

        Setting::set('janela_envio', [
            'inicio' => $state['inicio'],
            'fim' => $state['fim'],
            'dias' => array_map(intval(...), $state['dias']),
        ]);

        Notification::make()
            ->success()
            ->title('Configurações salvas')
            ->send();
    }

    public function testarKommo(): void
    {
        try {
            $pipelines = app(LeadRepository::class)->pipelines();

            $nomes = $pipelines
                ->map(fn ($p): string => "{$p->name} ({$p->id})")
                ->implode(' · ');

            Notification::make()
                ->success()
                ->title('Conexão com o Kommo OK')
                ->body("Funis encontrados: {$nomes}")
                ->send();
        } catch (KommoException $e) {
            Notification::make()
                ->danger()
                ->title('Falha na conexão com o Kommo')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function recarregarCampos(): void
    {
        try {
            $map = app(CustomFieldMap::class);
            $map->refresh();

            $this->mapaDeCampos = $map->all();

            Notification::make()
                ->success()
                ->title('Mapa de campos recarregado')
                ->body(count($this->mapaDeCampos) . ' campos personalizados encontrados no Kommo.')
                ->send();
        } catch (KommoException $e) {
            Notification::make()
                ->danger()
                ->title('Falha ao ler os campos do Kommo')
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testarKommo')
                ->label('Testar conexão Kommo')
                ->icon(Heroicon::OutlinedSignal)
                ->action('testarKommo'),
            Action::make('recarregarCampos')
                ->label('Recarregar mapa de campos')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action('recarregarCampos'),
        ];
    }
}
