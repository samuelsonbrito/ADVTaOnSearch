<?php

use App\Jobs\MonitorarAlvoJob;
use App\Models\Communication;
use App\Models\MonitoringTarget;
use App\Notifications\NovasComunicacoes;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\GatewayEmMemoria;

it('enfileira um job por alvo ativo', function () {
    Queue::fake();
    $ativos = MonitoringTarget::factory()->count(2)->create();
    MonitoringTarget::factory()->inativo()->create();

    $this->artisan('djen:monitorar')
        ->expectsOutputToContain('2 alvo(s) enviado(s) para a fila')
        ->assertSuccessful();

    Queue::assertPushed(MonitorarAlvoJob::class, 2);
    Queue::assertPushed(MonitorarAlvoJob::class, fn ($job) => $job->alvo->is($ativos[0]));
});

it('aceita alvos específicos', function () {
    Queue::fake();
    [$a, $b] = MonitoringTarget::factory()->count(2)->create();

    $this->artisan('djen:monitorar', ['--alvo' => [$b->id]])->assertSuccessful();

    Queue::assertPushed(MonitorarAlvoJob::class, 1);
    Queue::assertPushed(MonitorarAlvoJob::class, fn ($job) => $job->alvo->is($b));
});

it('executa sem fila com --sync, e a segunda execução não duplica avisos', function () {
    Notification::fake();
    $gateway = new GatewayEmMemoria;
    $gateway->adicionar('h1');
    $this->app->instance(ComunicacaoGateway::class, $gateway);
    $alvo = MonitoringTarget::factory()->create();

    $this->artisan('djen:monitorar', ['--sync' => true])->expectsOutputToContain('1 alvo(s) verificado(s)');
    $this->artisan('djen:monitorar', ['--sync' => true])->assertSuccessful();

    Notification::assertSentToTimes($alvo->user, NovasComunicacoes::class, 1);
});

it('agenda o monitoramento diário no horário e fuso configurados', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn (Event $e) => str_contains($e->command, 'djen:monitorar'));

    expect($evento)->not->toBeNull()
        ->and($evento->expression)->toBe('0 7 * * *')
        ->and($evento->timezone)->toBe('America/Manaus')
        ->and($evento->withoutOverlapping)->toBeTrue();
});

it('agenda a limpeza (LGPD) das comunicações órfãs', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn (Event $e) => str_contains($e->command, 'model:prune'));

    expect($evento)->not->toBeNull()
        ->and($evento->command)->toContain(Communication::class);
});

it('no modo --sync, a falha de um alvo não impede os demais', function () {
    Notification::fake();
    $gateway = new GatewayEmMemoria;
    $gateway->adicionar('h1');
    $this->app->instance(ComunicacaoGateway::class, $gateway);
    [$a, $b] = MonitoringTarget::factory()->count(2)->create();

    // O envio de e-mail do primeiro alvo falha (ex.: SMTP fora do ar).
    Notification::shouldReceive('send')->andReturnUsing(function ($notifiable) use ($a) {
        if ($notifiable->is($a->user)) {
            throw new RuntimeException('Connection could not be established with host "mailpit:1025"');
        }
    });

    $this->artisan('djen:monitorar', ['--sync' => true])
        ->expectsOutputToContain('mailpit:1025')
        ->expectsOutputToContain('2 alvo(s) verificado(s), 1 com erro.')
        ->assertFailed();

    // O segundo alvo foi verificado e notificado; o aviso do primeiro fica pendente.
    expect($b->alertas()->whereNotNull('notificada_em')->count())->toBe(1)
        ->and($a->alertas()->whereNull('notificada_em')->count())->toBe(1);
});
