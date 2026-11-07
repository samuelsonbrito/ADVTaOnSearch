<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastreio das buscas da aba Consulta (model App\Models\RegistroBusca).
 * LGPD: IP mascarado, visitante pseudonimizado (HMAC) e retenção limitada
 * (djen.rastreio.dias_retencao, via model:prune).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Origem (ver App\Support\Rastreio\Origem)
            $table->string('visitante', 16);
            $table->string('ip_mascarado', 45)->nullable();
            $table->string('dispositivo', 10);
            $table->string('navegador', 30)->nullable();
            $table->string('sistema', 30)->nullable();
            $table->boolean('bot')->default(false);
            $table->string('referer_host', 120)->nullable();

            // Critério principal: processo | oab | parte | advogado | tribunal
            $table->string('criterio', 20)->nullable();
            $table->string('oab', 12)->nullable();
            $table->char('uf', 2)->nullable();
            $table->string('parte', 120)->nullable();
            $table->string('advogado', 120)->nullable();
            $table->string('processo', 20)->nullable();
            $table->string('tribunal', 20)->nullable();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->unsignedSmallInteger('pagina')->default(1);

            // ok | vazio | erro | invalido
            $table->string('status', 10);
            $table->string('erro_tipo', 20)->nullable();
            $table->unsignedInteger('total_resultados')->nullable();
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->boolean('do_cache')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['criterio', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['visitante', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
