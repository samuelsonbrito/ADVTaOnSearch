<?php

use App\Jobs\MonitorarAlvoJob;
use App\Models\Atividade;
use App\Models\MonitoringTarget;
use App\Models\User;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenBlockedException;
use App\Support\Rastreio\TipoEvento;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\GatewayEmMemoria;

function tipos(): array
{
    return Atividade::orderBy('id')->where('tipo', '!=', TipoEvento::PAGINA_VISTA)->pluck('tipo')->all();
}

it('registra login, falha de login e logout', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'errada']);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('logout'));

    expect(tipos())->toBe([TipoEvento::USUARIO_LOGIN_FALHOU, TipoEvento::USUARIO_LOGIN, TipoEvento::USUARIO_LOGOUT])
        ->and(Atividade::where('tipo', TipoEvento::USUARIO_LOGIN)->sole()->user_id)->toBe($user->id)
        ->and(Atividade::where('tipo', TipoEvento::USUARIO_LOGIN_FALHOU)->sole()->dados)->toBe(['conta_existe' => true]);
});

it('registra o cadastro', function () {
    $this->post(route('register.store'), [
        'name' => 'Nova Pessoa', 'email' => 'nova@exemplo.com', 'password' => 'password', 'password_confirmation' => 'password',
    ]);

    expect(tipos())->toContain(TipoEvento::USUARIO_CADASTRADO);
});

it('registra o ciclo de um alvo', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('monitoramento.alvos.store'), ['tipo' => 'oab', 'valor' => '12345', 'uf' => 'AM']);
    $alvo = MonitoringTarget::sole();
    $this->patch(route('monitoramento.alvos.update', $alvo), ['ativo' => false]);
    $this->patch(route('monitoramento.alvos.update', $alvo), ['ativo' => true]);
    $this->post(route('monitoramento.alvos.verificar', $alvo));
    $this->delete(route('monitoramento.alvos.destroy', $alvo));

    expect(tipos())->toBe([
        TipoEvento::ALVO_CRIADO, TipoEvento::ALVO_PAUSADO, TipoEvento::ALVO_RETOMADO,
        TipoEvento::ALVO_VERIFICACAO_MANUAL, TipoEvento::ALVO_EXCLUIDO,
    ])
        ->and(Atividade::where('tipo', TipoEvento::ALVO_CRIADO)->sole()->dados)->toEqual(['alvo_id' => $alvo->id, 'tipo' => 'oab']);
});

it('registra a execução do monitoramento e a notificação, sem origem de navegador', function () {
    Notification::fake();
    $gateway = new GatewayEmMemoria;
    $gateway->adicionar('h1');
    $gateway->adicionar('h2');
    $this->app->instance(ComunicacaoGateway::class, $gateway);
    $alvo = MonitoringTarget::factory()->oab('12345')->create();

    MonitorarAlvoJob::dispatchSync($alvo);

    $executado = Atividade::where('tipo', TipoEvento::MONITORAMENTO_EXECUTADO)->sole();
    $notificacao = Atividade::where('tipo', TipoEvento::NOTIFICACAO_ENVIADA)->sole();

    expect($executado->user_id)->toBe($alvo->user_id)
        ->and($executado->dados)->toMatchArray(['alvo_id' => $alvo->id, 'novos' => 2])
        ->and($executado->visitante)->toBeNull()
        ->and($notificacao->dados)->toEqual(['alvo_id' => $alvo->id, 'quantidade' => 2, 'canais' => ['email']]);
});

it('registra a falha do monitoramento', function () {
    $gateway = new GatewayEmMemoria;
    $gateway->erro = new DjenBlockedException('403');
    $this->app->instance(ComunicacaoGateway::class, $gateway);
    $alvo = MonitoringTarget::factory()->oab('12345')->create();

    try {
        MonitorarAlvoJob::dispatchSync($alvo);
    } catch (Throwable) {
    }

    expect(Atividade::where('tipo', TipoEvento::MONITORAMENTO_FALHOU)->sole()->dados)
        ->toMatchArray(['alvo_id' => $alvo->id, 'erro' => 'DjenBlockedException']);
});

it('registra a exclusão da conta mantendo o id em dados', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    $e = Atividade::where('tipo', TipoEvento::CONTA_EXCLUIDA)->sole();

    expect($e->user_id)->toBeNull()->and($e->dados)->toBe(['usuario_id' => $user->id]);
});
