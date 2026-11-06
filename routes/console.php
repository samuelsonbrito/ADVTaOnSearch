<?php

use App\Models\Communication;
use App\Models\Exportacao;
use Illuminate\Support\Facades\Schedule;

// Monitoramento diário dos alvos (horário e fuso em config/djen.php).
Schedule::command('djen:monitorar')
    ->dailyAt((string) config('djen.monitoring.time'))
    ->timezone((string) config('djen.monitoring.timezone'))
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Monitoramento DJEN');

// LGPD: apaga comunicações que nenhum alvo referencia mais e exportações
// (registro + arquivo) mais antigas que a retenção configurada.
Schedule::command('model:prune', ['--model' => [Communication::class, Exportacao::class]])
    ->dailyAt('03:17')
    ->description('Limpeza LGPD (comunicações órfãs e exportações expiradas)');
