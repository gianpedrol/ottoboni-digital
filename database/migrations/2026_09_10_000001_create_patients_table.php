<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('telefone', 20)->index();
            $table->string('email')->nullable();
            $table->text('cpf')->nullable(); // cifrado (LGPD)
            $table->date('data_nascimento')->nullable();
            $table->string('sexo', 1)->nullable();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('procedimento_interesse')->nullable();
            $table->string('origem')->nullable();
            $table->string('indicacao')->nullable();
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('kommo_contact_id')->nullable()->index();
            $table->unsignedBigInteger('kommo_lead_id')->nullable();
            $table->string('kommo_sync_status', 20)->default('pendente');
            $table->json('kommo_sync_payload')->nullable();
            $table->timestamp('kommo_synced_at')->nullable();
            $table->boolean('demo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
