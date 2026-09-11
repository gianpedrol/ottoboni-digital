<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nome');
            $table->string('tipo', 20); // consulta, retorno, procedimento, cirurgia
            $table->decimal('valor', 10, 2);
            $table->unsignedSmallInteger('duracao_min')->default(30);
            $table->decimal('repasse_pct', 5, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->boolean('demo')->default(false);
            $table->timestamps();
        });

        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('descricao');
            $table->decimal('valor_total', 12, 2);
            $table->string('forma_pagamento', 20); // pix, cartao, boleto, dinheiro
            $table->string('origem', 20)->default('manual'); // contrato, consulta, procedimento, manual
            $table->unsignedBigInteger('kommo_lead_id')->nullable();
            $table->boolean('demo')->default(false);
            $table->timestamps();
        });

        Schema::create('receivable_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receivable_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');
            $table->decimal('valor', 12, 2);
            $table->date('vencimento')->index();
            $table->date('pago_em')->nullable();
            $table->decimal('valor_pago', 12, 2)->nullable();
            $table->string('status', 20)->default('aberto'); // aberto, pago, atrasado, cancelado
            $table->boolean('demo')->default(false);
            $table->timestamps();
        });

        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->string('fornecedor');
            $table->string('categoria', 40);
            $table->string('descricao')->nullable();
            $table->decimal('valor', 12, 2);
            $table->date('vencimento')->index();
            $table->date('pago_em')->nullable();
            $table->boolean('recorrente')->default(false);
            $table->string('status', 20)->default('aberto'); // aberto, pago, atrasado, cancelado
            $table->boolean('demo')->default(false);
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('apelido');
            $table->string('banco');
            $table->string('agencia', 10)->nullable();
            $table->string('conta', 20)->nullable();
            $table->decimal('saldo_inicial', 12, 2)->default(0);
            $table->boolean('demo')->default(false);
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('data')->index();
            $table->string('descricao');
            $table->decimal('valor', 12, 2); // positivo = crédito, negativo = débito
            $table->string('fitid')->nullable(); // id do lançamento no OFX
            $table->nullableMorphs('conciliavel'); // parcela a receber ou conta a pagar
            $table->timestamp('conciliado_em')->nullable();
            $table->boolean('demo')->default(false);
            $table->timestamps();

            // O mesmo OFX importado duas vezes não duplica lançamento.
            $table->unique(['bank_account_id', 'fitid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('payables');
        Schema::dropIfExists('receivable_installments');
        Schema::dropIfExists('receivables');
        Schema::dropIfExists('services');
    }
};
