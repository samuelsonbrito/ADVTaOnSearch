<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada requisição HTTP real feita à Comunica API (cache não conta).
 * Model App\Models\ChamadaDjen. Mostra saúde, latência e consumo da API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('djen_api_calls', function (Blueprint $table) {
            $table->id();
            // consulta | monitoramento | exportacao | comando | outro
            $table->string('origem', 20);
            $table->string('endpoint', 60);
            $table->jsonb('parametros');
            $table->unsignedSmallInteger('status')->nullable();
            // sucesso | limitado | bloqueado | indisponivel | recusado | invalido
            $table->string('resultado', 15);
            $table->unsignedInteger('duracao_ms');
            $table->unsignedInteger('itens')->nullable();
            $table->unsignedInteger('ratelimit_restante')->nullable();
            $table->string('erro')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['resultado', 'created_at']);
            $table->index(['origem', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('djen_api_calls');
    }
};
