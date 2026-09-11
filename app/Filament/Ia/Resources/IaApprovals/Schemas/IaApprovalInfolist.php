<?php

namespace App\Filament\Ia\Resources\IaApprovals\Schemas;

use App\Models\IaApproval;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detalhe de uma revisão passada. Mostra o que a agente escreveu e o que foi
 * enviado de fato — é essa comparação que explica a nota.
 */
class IaApprovalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contexto')
                ->schema([
                    TextEntry::make('created_at')->label('Chegou em')->dateTime('d/m/Y H:i'),
                    TextEntry::make('doctor.nome')->label('Agente'),
                    TextEntry::make('canal')->label('Canal')->badge(),
                    TextEntry::make('intent')->label('Assunto')->badge(),
                    TextEntry::make('motivo_fila')->label('Por que foi para a fila')->badge(),
                    TextEntry::make('ig_username')
                        ->label('Paciente')
                        ->formatStateUsing(fn (?string $state): string => $state ? '@' . $state : '—'),
                    TextEntry::make('texto_da_pessoa')
                        ->label('O que escreveram')
                        ->state(fn (IaApproval $r): string => $r->textoDaPessoa() ?: '—')
                        ->columnSpanFull(),
                    TextEntry::make('post_permalink')
                        ->label('Post')
                        ->url(fn (?string $state): ?string => $state)
                        ->openUrlInNewTab()
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                ->columns(3),

            Section::make('O que a agente propôs')
                ->schema([
                    TextEntry::make('rascunho_comentario')->label('Comentário')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('rascunho_dm')->label('Direct')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('O que foi enviado')
                ->schema([
                    TextEntry::make('final_comentario')->label('Comentário')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('final_dm')->label('Direct')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('Revisão')
                ->schema([
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('grau_edicao')->label('Grau de edição')->badge()->placeholder('—'),
                    TextEntry::make('score')->label('Nota')->numeric(2)->placeholder('—'),
                    TextEntry::make('similaridade')->label('Similaridade')->numeric(3)->placeholder('—'),
                    TextEntry::make('revisor.name')->label('Revisado por')->placeholder('—'),
                    TextEntry::make('revisado_em')->label('Revisado em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('observacao_humano')->label('Observação')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('modelo')->label('Modelo'),
                    TextEntry::make('promptVersion.versao')
                        ->label('Instruções')
                        ->formatStateUsing(fn (?int $state): string => $state ? 'v' . $state : '—'),
                    TextEntry::make('erro')->label('Erro no envio')->placeholder('—')->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }
}
