<?php

namespace App\Filament\Resources\Receivables\Pages;

use App\Filament\Concerns\ComSeloDemonstracao;
use App\Filament\Resources\Receivables\ReceivableResource;
use App\Models\Receivable;
use App\Services\Financeiro\GeradorDeParcelas;
use App\Support\Financeiro;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateReceivable extends CreateRecord
{
    use ComSeloDemonstracao;

    protected static string $resource = ReceivableResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $parcelas = (int) ($data['parcelas'] ?? 1);
        $primeiroVencimento = $data['primeiro_vencimento'] ?? Financeiro::hoje()->toDateString();

        unset($data['parcelas'], $data['primeiro_vencimento']);

        $data['demo'] = Financeiro::registroDemo();

        return DB::transaction(function () use ($data, $parcelas, $primeiroVencimento): Receivable {
            $receivable = Receivable::query()->create($data);

            app(GeradorDeParcelas::class)->gerar($receivable, $parcelas, $primeiroVencimento);

            return $receivable;
        });
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
