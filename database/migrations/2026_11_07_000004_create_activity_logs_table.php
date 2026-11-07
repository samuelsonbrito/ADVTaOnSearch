<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de atividade: páginas vistas, login/cadastro, alvos, alertas,
 * notificações, exportações... (model App\Models\Atividade; tipos em
 * App\Support\Rastreio\TipoEvento). Mesmas regras de LGPD de search_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo', 40);
            $table->jsonb('dados')->nullable();

            // Origem: nula para eventos sem requisição (jobs, comandos)
            $table->string('visitante', 16)->nullable();
            $table->string('ip_mascarado', 45)->nullable();
            $table->string('dispositivo', 10)->nullable();
            $table->string('navegador', 30)->nullable();
            $table->string('sistema', 30)->nullable();
            $table->boolean('bot')->default(false);
            $table->string('referer_host', 120)->nullable();
            $table->string('rota', 80)->nullable();
            $table->string('caminho')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['tipo', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
