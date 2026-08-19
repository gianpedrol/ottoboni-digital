<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export simples de linhas já montadas (usado por Atendimentos e Relatórios).
 */
class ArrayExport implements FromArray, WithHeadings
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $headings
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $headings,
    ) {}

    public function array(): array
    {
        return array_map(array_values(...), $this->rows);
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
