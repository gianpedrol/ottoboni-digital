<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FollowupCanal: string implements HasLabel
{
    case Auto = 'auto';
    case Instagram = 'instagram';
    case KommoBot = 'kommo_bot';
    case KommoTask = 'kommo_task';

    public function getLabel(): string
    {
        return match ($this) {
            self::Auto => 'Automático (via n8n)',
            self::Instagram => 'Instagram DM (via n8n)',
            self::KommoBot => 'Salesbot do Kommo',
            self::KommoTask => 'Tarefa no Kommo (humano)',
        };
    }
}
