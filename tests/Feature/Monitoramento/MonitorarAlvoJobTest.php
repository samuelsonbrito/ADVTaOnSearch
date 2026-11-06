<?php

use App\Jobs\MonitorarAlvoJob;
use App\Models\Alerta;
use App\Models\MonitoringTarget;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\NovasComunicacoes;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenBlockedException;
use App\Services\Djen\Exceptions\DjenMappingPendingException;
use App\Services\Djen\Exceptions\DjenRateLimitedException;
use App\Services\Djen\Exceptions\DjenUnavailableException;
use App\Services\Monitoramento\MonitorarAlvo;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Notification;
use Tests\Support\GatewayEmMemoria;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-06 07:00', 'America/Manaus'));
    $this->gateway = new GatewayEmMemoria;
    $this->app->instance(ComunicacaoGateway::class, $this->gateway);
    Notification::fake();
});

it('usa os middlewares RateLimited("djen") e WithoutOverlapping', function () {
    $middleware = (new MonitorarAlvoJob(MonitoringTarget::factory()->create()))->middleware();

    expect($middleware[0])->toBeInstanceOf(RateLimited::class)
        ->and((fn () => $this->limiterName)->call($middleware[0]))->toBe('djen')
        ->and($middleware[1])->toBeInstanceOf(WithoutOverlapping::class);
});

it('notifica o dono só sobre as comunicações novas, e nunca duas vezes', function () {
    $alvo = MonitoringTarget::factory()->create();
    $this->gateway->adicionar('h1', '2026-11-05');
    $this->gateway->adicionar('h2', '2026-11-06');

    MonitorarAlvoJob::dispatchSync($alvo);

    Notification::assertSentToTimes($alvo->user, NovasComunicacoes::class, 1);
    Notification::assertSentTo($alvo->user, NovasComunicacoes::class, fn (NovasComunicacoes $n) => $n->alvo->is($alvo)
        && $n->alertas->map(fn (Alerta $a) => $a->communication->hash)->all() === ['h2', 'h1']); // mais recente primeiro
    expect(Alerta::whereNull('notificada_em')->count())->toBe(0);

    // Segunda execução sem novidades: nenhum aviso novo.
    MonitorarAlvoJob::dispatchSync($alvo);
    Notification::assertSentToTimes($alvo->user, NovasComunicacoes::class, 1);

    // Chega uma comunicação nova: avisa só dela.
    $this->gateway->adicionar('h3');
    MonitorarAlvoJob::dispatchSync($alvo);

    Notification::assertSentToTimes($alvo->user, NovasComunicacoes::class, 2);
    Notification::assertSentTo($alvo->user, NovasComunicacoes::class, fn (NovasComunicacoes $n) => $n->alertas->map(fn ($a) => $a->communication->hash)->all() === ['h3']);
});

it('não notifica quando nada é encontrado', function () {
    MonitorarAlvoJob::dispatchSync(MonitoringTarget::factory()->create());

    Notification::assertNothingSent();
});

it('ignora alvos pausados', function () {
    $this->gateway->adicionar('h1');

    MonitorarAlvoJob::dispatchSync(MonitoringTarget::factory()->inativo()->create());

    expect($this->gateway->filtros)->toBeEmpty();
    Notification::assertNothingSent();
});

it('reenvia na próxima execução se o aviso anterior falhou', function () {
    $alvo = MonitoringTarget::factory()->create();
    $this->gateway->adicionar('h1');
    app(MonitorarAlvo::class)->executar($alvo); // alerta criado, aviso não enviado

    MonitorarAlvoJob::dispatchSync($alvo);

    Notification::assertSentTo($alvo->user, NovasComunicacoes::class, fn ($n) => $n->alertas->count() === 1);
});

it('devolve o job para a fila quando o CNJ limita (429)', function () {
    $this->gateway->erro = new DjenRateLimitedException('429', 42);
    $job = Mockery::mock(MonitorarAlvoJob::class.'[release]', [MonitoringTarget::factory()->create()]);
    $job->shouldReceive('release')->once()->with(42);

    $job->handle(app(MonitorarAlvo::class));

    Notification::assertNothingSent();
});

it('falha sem novas tentativas em erros permanentes', function (Throwable $erro) {
    $this->gateway->erro = $erro;
    $job = Mockery::mock(MonitorarAlvoJob::class.'[fail]', [MonitoringTarget::factory()->create()]);
    $job->shouldReceive('fail')->once()->with($erro);

    $job->handle(app(MonitorarAlvo::class));
})->with([
    'mapeamento pendente' => [new DjenMappingPendingException],
    'bloqueado' => [new DjenBlockedException('403', 403)],
]);

it('propaga indisponibilidade para o retry com backoff da fila', function () {
    $this->gateway->erro = new DjenUnavailableException('timeout');
    $job = new MonitorarAlvoJob(MonitoringTarget::factory()->create());

    expect(fn () => $job->handle(app(MonitorarAlvo::class)))
        ->toThrow(DjenUnavailableException::class)
        ->and($job->tries)->toBe(5)
        ->and($job->backoff)->toBe([60, 300, 900]);
});

it('envia por e-mail e, se houver número, por WhatsApp', function () {
    $semWhats = MonitoringTarget::factory()->for(User::factory())->create();
    $comWhats = MonitoringTarget::factory()->for(User::factory()->state(['whatsapp' => '5592999998888']))->create();
    $this->gateway->adicionar('h1');

    MonitorarAlvoJob::dispatchSync($semWhats);
    MonitorarAlvoJob::dispatchSync($comWhats);

    Notification::assertSentTo($semWhats->user, NovasComunicacoes::class, fn ($n, array $canais) => $canais === ['mail']);
    Notification::assertSentTo($comWhats->user, NovasComunicacoes::class, fn ($n, array $canais) => $canais === ['mail', WhatsAppChannel::class]);
});
