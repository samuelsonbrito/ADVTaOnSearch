<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comunicações encontradas pelo monitoramento. Compartilhadas entre alvos
 * (deduplicadas pelo hash). LGPD: só os campos exibidos no painel; linhas
 * sem nenhum alvo vinculado são apagadas (Communication::prunable()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->string('hash', 191)->unique();
            $table->string('tribunal', 20);
            $table->string('orgao_julgador')->nullable();
            $table->string('tipo_comunicacao', 100)->nullable();
            $table->string('tipo_documento', 100)->nullable();
            $table->date('data_disponibilizacao');
            $table->string('numero_processo', 30);
            $table->jsonb('destinatarios');
            $table->jsonb('advogados');
            $table->text('texto_html');
            $table->string('link_documento', 2048)->nullable();
            $table->timestamps();

            $table->index('data_disponibilizacao');
            $table->index('numero_processo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
