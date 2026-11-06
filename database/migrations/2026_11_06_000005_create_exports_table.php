<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exportações (model App\Models\Exportacao). LGPD: registros e arquivos são
 * apagados após djen.exportacao.dias_retencao (Exportacao::prunable()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('origem', 20);   // consulta | monitoramento
            $table->string('formato', 10);  // csv | xlsx | pdf
            $table->string('status', 20)->default('pendente');
            // O que exportar: filtros + seleção (ou "todos"). Ver ColetorExportacao.
            $table->jsonb('parametros');
            $table->unsignedInteger('total_itens')->nullable();
            $table->string('arquivo')->nullable();
            $table->string('erro')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
