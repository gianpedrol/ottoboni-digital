<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TipoRegistroProntuario: string implements HasColor, HasIcon, HasLabel
{
    case Anamnese = 'anamnese';
    case Evolucao = 'evolucao';
    case Prescricao = 'prescricao';
    case Exame = 'exame';
    case Atestado = 'atestado';
    case Foto = 'foto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Anamnese => 'Anamnese',
            self::Evolucao => 'Evolução',
            self::Prescricao => 'Prescrição',
            self::Exame => 'Pedido de exame',
            self::Atestado => 'Atestado',
            self::Foto => 'Fotos antes/depois',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Anamnese => 'info',
            self::Evolucao => 'gray',
            self::Prescricao => 'success',
            self::Exame => 'warning',
            self::Atestado => 'danger',
            self::Foto => 'primary',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Anamnese => Heroicon::OutlinedClipboardDocumentList,
            self::Evolucao => Heroicon::OutlinedPencilSquare,
            self::Prescricao => Heroicon::OutlinedBeaker,
            self::Exame => Heroicon::OutlinedDocumentMagnifyingGlass,
            self::Atestado => Heroicon::OutlinedDocumentCheck,
            self::Foto => Heroicon::OutlinedCamera,
        };
    }

    public function tituloPadrao(): string
    {
        return match ($this) {
            self::Anamnese => 'Anamnese inicial',
            self::Evolucao => 'Evolução',
            self::Prescricao => 'Prescrição',
            self::Exame => 'Pedido de exames',
            self::Atestado => 'Atestado médico',
            self::Foto => 'Registro fotográfico',
        };
    }

    public function imprimivel(): bool
    {
        return in_array($this, [self::Prescricao, self::Atestado, self::Exame], true);
    }
}
