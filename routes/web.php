<?php

use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\Monitoramento\AlertaController;
use App\Http\Controllers\Monitoramento\AlvoController;
use App\Http\Controllers\Monitoramento\MonitoramentoController;
use App\Http\Controllers\Monitoramento\PreferenciasController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/consulta')->name('home');

Route::get('consulta', ConsultaController::class)
    ->middleware('throttle:djen-consulta')
    ->name('consulta.index');

Route::middleware(['auth', 'verified'])->group(function () {
    // O kit chama a página inicial logada de "dashboard"; aqui é o monitoramento.
    Route::redirect('dashboard', '/monitoramento')->name('dashboard');

    Route::prefix('monitoramento')->name('monitoramento.')->group(function () {
        Route::get('/', MonitoramentoController::class)->name('index');

        Route::post('alvos', [AlvoController::class, 'store'])->name('alvos.store');
        Route::patch('alvos/{alvo}', [AlvoController::class, 'update'])->name('alvos.update');
        Route::delete('alvos/{alvo}', [AlvoController::class, 'destroy'])->name('alvos.destroy');
        Route::post('alvos/{alvo}/verificar', [AlvoController::class, 'verificar'])
            ->middleware('throttle:6,1')
            ->name('alvos.verificar');

        Route::patch('alertas/{alerta}', [AlertaController::class, 'update'])->name('alertas.update');
        Route::post('alertas/marcar-lidas', [AlertaController::class, 'marcarTodasLidas'])->name('alertas.marcar-lidas');

        Route::put('notificacoes', [PreferenciasController::class, 'notificacoes'])->name('notificacoes.update');
        Route::delete('dados', [PreferenciasController::class, 'excluirDados'])->name('dados.destroy');
    });
});

require __DIR__.'/settings.php';
