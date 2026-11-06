<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alertas: cada linha é uma comunicação encontrada para um alvo.
 * O índice único garante que a mesma comunicação nunca gere dois alertas
 * para o mesmo alvo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_target', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitoring_target_id')->constrained()->cascadeOnDelete();
            $table->timestamp('lida_em')->nullable();
            $table->timestamp('notificada_em')->nullable();
            $table->timestamps();

            $table->unique(['communication_id', 'monitoring_target_id']);
            $table->index(['monitoring_target_id', 'lida_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_target');
    }
};
