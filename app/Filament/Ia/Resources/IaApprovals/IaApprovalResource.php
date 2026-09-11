<?php

namespace App\Filament\Ia\Resources\IaApprovals;

use App\Filament\Ia\Resources\IaApprovals\Pages\ListIaApprovals;
use App\Filament\Ia\Resources\IaApprovals\Schemas\IaApprovalInfolist;
use App\Filament\Ia\Resources\IaApprovals\Tables\IaApprovalsTable;
use App\Models\IaApproval;
use App\Models\User;
use App\Support\AgenteSelecionado;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Histórico de tudo que a agente falou ou quis falar — inclusive o que saiu
 * automático. É a memória auditável do projeto: quem aprovou, o que ela tinha
 * escrito, o que foi enviado de fato e quanto o humano precisou mexer.
 */
class IaApprovalResource extends Resource
{
    protected static ?string $model = IaApproval::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Revisão';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'revisão';

    protected static ?string $pluralModelLabel = 'revisões';

    protected static ?string $navigationLabel = 'Histórico';

    public static function infolist(Schema $schema): Schema
    {
        return IaApprovalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IaApprovalsTable::configure($table);
    }

    /** @return Builder<IaApproval> */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = Auth::user();

        // Escopo aplicado no filtro da consulta, nunca só na view.
        return parent::getEloquentQuery()
            ->whereIn('doctor_id', AgenteSelecionado::permitidos($user));
    }

    public static function canCreate(): bool
    {
        return false; // quem cria item é o n8n, pela API
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIaApprovals::route('/'),
        ];
    }
}
