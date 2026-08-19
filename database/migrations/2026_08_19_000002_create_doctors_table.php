<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('agente', 20)->unique();
            $table->unsignedBigInteger('kommo_pipeline_id')->unique();
            $table->string('ig_user_id')->nullable();
            $table->unsignedBigInteger('kommo_bot_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
