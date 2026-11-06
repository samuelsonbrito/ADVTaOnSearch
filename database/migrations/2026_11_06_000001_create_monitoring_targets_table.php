<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // oab | parte | processo (App\Enums\TipoAlvo)
            $table->string('tipo', 20);
            // OAB: só dígitos · parte: nome · processo: 20 dígitos
            $table->string('valor', 120);
            $table->char('uf', 2)->nullable();
            $table->string('apelido', 80)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultima_verificacao_em')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tipo', 'valor', 'uf']);
            $table->index(['ativo', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_targets');
    }
};
