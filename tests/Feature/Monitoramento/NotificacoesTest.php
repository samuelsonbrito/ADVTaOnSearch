<?php

use App\Models\Alerta;
use App\Models\Communication;
use App\Models\MonitoringTarget;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\NovasComunicacoes;
use App\Notifications\WhatsApp\LogWhatsAppDriver;
use App\Notifications\WhatsApp\WhatsAppDriver;
use App\Notifications\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Tests\Support\GatewayEmMemoria;

function notificacaoCom(int $quantidade, ?User $user = null): NovasComunicacoes
{
    $alvo = MonitoringTarget::factory()->for($user ?? User::factory())->oab('12345', 'AM')->create();
    $gateway = new GatewayEmMemoria;

    $alertas = collect(range(1, $quantidade))->map(function (int $i) use ($gateway, $alvo) {
        $c = Communication::create(Communication::atributosDe($gateway->adicionar("h{$i}", '2026-11-0'.(($i % 9) + 1))));

        return Alerta::create(['communication_id' => $c->id, 'monitoring_target_id' => $alvo->id])->load('communication');
    });

    return new NovasComunicacoes($alvo, $alertas);
}

it('monta o e-mail com as comunicações e o link do painel', function () {
    $user = User::factory()->create(['name' => 'Helena']);
    $n = notificacaoCom(2, $user);

    $mail = $n->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('2 novas comunicações — OAB 12345/AM')
        ->and($html)->toContain('Olá, Helena!')
        ->and($html)->toContain('0001234-56.2024.8.04.0001')
        ->and($html)->toContain('TJAM')
        ->and($mail->actionUrl)->toBe(route('monitoramento.index', ['alvo' => $n->alvo->id]));
});

it('limita a lista do e-mail', function () {
    $n = notificacaoCom(NovasComunicacoes::LIMITE_EMAIL + 2);

    $linhas = collect($n->toMail($n->alvo->user)->introLines);

    expect($linhas->filter(fn ($l) => str_starts_with($l, '•')))->toHaveCount(NovasComunicacoes::LIMITE_EMAIL)
        ->and($linhas->last())->toBe('… e mais 2.');
});

it('usa singular com uma comunicação', function () {
    $n = notificacaoCom(1);

    expect($n->toMail($n->alvo->user)->subject)->toBe('1 nova comunicação — OAB 12345/AM')
        ->and($n->toWhatsApp($n->alvo->user)->texto)->toContain('1 nova comunicação no DJEN para OAB 12345/AM');
});

it('envia pelo WhatsAppChannel usando o driver configurado', function () {
    $user = User::factory()->create(['whatsapp' => '5592999998888']);
    $n = notificacaoCom(3, $user);
    $driver = Mockery::mock(WhatsAppDriver::class);
    $driver->shouldReceive('enviar')->once()->withArgs(fn (string $numero, WhatsAppMessage $m) => $numero === '5592999998888'
        && str_contains($m->texto, '3 novas comunicações'));

    (new WhatsAppChannel($driver))->send($user, $n);
});

it('não envia WhatsApp sem número cadastrado', function () {
    $user = User::factory()->create(['whatsapp' => null]);
    $driver = Mockery::mock(WhatsAppDriver::class);
    $driver->shouldNotReceive('enviar');

    (new WhatsAppChannel($driver))->send($user, notificacaoCom(1, $user));
});

it('o driver "log" registra a mensagem sem expor o número', function () {
    Log::spy();
    config(['djen.whatsapp.driver' => 'log']);

    app(WhatsAppDriver::class)->enviar('5592999998888', new WhatsAppMessage('Olá'));

    Log::shouldHaveReceived('info')->once()->withArgs(fn (string $msg, array $ctx) => str_contains($msg, 'whatsapp:log')
        && $ctx === ['para' => '*********8888', 'texto' => 'Olá']);
    expect(app(WhatsAppDriver::class))->toBeInstanceOf(LogWhatsAppDriver::class);
});

it('recusa driver de WhatsApp desconhecido', function () {
    config(['djen.whatsapp.driver' => 'zapi']);

    expect(fn () => app(WhatsAppDriver::class))->toThrow(InvalidArgumentException::class, 'zapi');
});

it('traduz o modelo de e-mail para pt_BR', function () {
    app()->setLocale('pt_BR');
    $n = notificacaoCom(1);

    $texto = (string) $n->toMail($n->alvo->user)->render();

    expect($texto)->toContain('Atenciosamente,')
        ->and($texto)->toContain('Se não conseguir clicar no botão')
        ->and($texto)->not->toContain('Regards');
});
