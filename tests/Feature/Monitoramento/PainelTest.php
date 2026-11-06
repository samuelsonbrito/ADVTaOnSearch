<?php

use App\Jobs\MonitorarAlvoJob;
use App\Models\Alerta;
use App\Models\Communication;
use App\Models\MonitoringTarget;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\GatewayEmMemoria;

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->dados = new GatewayEmMemoria;
});

/** Cria comunicação + alerta para o alvo. */
function alertaPara(MonitoringTarget $alvo, string $hash, string $data = '2026-11-06', bool $lida = false): Alerta
{
    $dados = test()->dados->adicionar($hash, $data);
    $c = Communication::firstOrCreate(['hash' => $hash], Communication::atributosDe($dados));

    return Alerta::create([
        'communication_id' => $c->id,
        'monitoring_target_id' => $alvo->id,
        'lida_em' => $lida ? now() : null,
    ]);
}

describe('acesso', function () {
    it('exige login', function () {
        $this->get(route('monitoramento.index'))->assertRedirect(route('login'));
        $this->post(route('monitoramento.alvos.store'))->assertRedirect(route('login'));
    });

    it('leva o dashboard e o pós-login ao monitoramento', function () {
        $this->actingAs($this->user)->get(route('dashboard'))->assertRedirect('/monitoramento');
        expect(config('fortify.home'))->toBe('/monitoramento');
    });
});

describe('painel', function () {
    it('lista alvos com contagem e as não lidas, mais recentes primeiro', function () {
        $alvo = MonitoringTarget::factory()->for($this->user)->oab('12345', 'AM')->create();
        alertaPara($alvo, 'antiga', '2026-11-01');
        alertaPara($alvo, 'nova', '2026-11-06');
        alertaPara($alvo, 'lida', '2026-11-05', lida: true);

        $this->actingAs($this->user)->get(route('monitoramento.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Monitoramento/Index')
                ->has('alvos', 1, fn (Assert $a) => $a
                    ->where('id', $alvo->id)
                    ->where('tipo', 'oab')
                    ->where('tipoRotulo', 'OAB')
                    ->where('valor', '12345')
                    ->where('uf', 'AM')
                    ->where('descricao', 'OAB 12345/AM')
                    ->where('ativo', true)
                    ->where('naoLidas', 2)
                    ->where('total', 3)
                    ->etc())
                ->where('totalNaoLidas', 2)
                ->where('filtros', ['alvo' => null, 'status' => 'nao_lidas'])
                ->has('alertas.data', 2)
                ->where('alertas.data.0.comunicacao.hash', 'nova')
                ->where('alertas.data.1.comunicacao.hash', 'antiga')
                ->has('alertas.data.0', fn (Assert $a) => $a
                    ->where('lida', false)
                    ->where('alvo', ['id' => $alvo->id, 'descricao' => 'OAB 12345/AM'])
                    ->whereType('recebidaEm', 'string')
                    ->has('comunicacao', fn (Assert $c) => $c
                        ->where('numeroProcesso', '0001234-56.2024.8.04.0001')
                        ->etc())
                    ->etc())
                ->where('alertas.meta.total', 2)
                ->has('tiposAlvo', 3)
                ->where('notificacoes.email', $this->user->email)
                ->where('notificacoes.horario', config('djen.monitoring.time')));
    });

    it('filtra por status e por alvo (histórico)', function () {
        $a = MonitoringTarget::factory()->for($this->user)->create();
        $b = MonitoringTarget::factory()->for($this->user)->create();
        alertaPara($a, 'a1');
        alertaPara($a, 'a2', lida: true);
        alertaPara($b, 'b1');

        $this->actingAs($this->user);

        $this->get(route('monitoramento.index', ['status' => 'todas']))
            ->assertInertia(fn (Assert $p) => $p->has('alertas.data', 3));
        $this->get(route('monitoramento.index', ['status' => 'lidas']))
            ->assertInertia(fn (Assert $p) => $p->has('alertas.data', 1)->where('alertas.data.0.comunicacao.hash', 'a2'));
        $this->get(route('monitoramento.index', ['status' => 'todas', 'alvo' => $b->id]))
            ->assertInertia(fn (Assert $p) => $p->has('alertas.data', 1)->where('filtros.alvo', $b->id));
    });

    it('pagina os alertas', function () {
        $alvo = MonitoringTarget::factory()->for($this->user)->create();
        foreach (range(1, 25) as $i) {
            alertaPara($alvo, "h{$i}");
        }

        $this->actingAs($this->user)->get(route('monitoramento.index', ['pagina' => 2]))
            ->assertInertia(fn (Assert $p) => $p
                ->has('alertas.data', 5)
                ->where('alertas.meta.pagina', 2)
                ->where('alertas.meta.ultimaPagina', 2));
    });

    it('não mostra dados de outros usuários', function () {
        $outro = MonitoringTarget::factory()->create();
        alertaPara($outro, 'alheia');

        $this->actingAs($this->user)->get(route('monitoramento.index', ['status' => 'todas']))
            ->assertInertia(fn (Assert $p) => $p->has('alvos', 0)->has('alertas.data', 0));

        $this->get(route('monitoramento.index', ['alvo' => $outro->id]))->assertSessionHasErrors('alvo');
    });
});

describe('alvos', function () {
    it('cadastra alvo normalizado e inicia a primeira verificação', function () {
        $this->actingAs($this->user)
            ->post(route('monitoramento.alvos.store'), ['tipo' => 'processo', 'valor' => '0001234-56.2024.8.04.0001', 'uf' => 'AM', 'apelido' => ' Caso X '])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $alvo = $this->user->monitoringTargets()->sole();
        expect($alvo->valor)->toBe('00012345620248040001')
            ->and($alvo->uf)->toBeNull()
            ->and($alvo->apelido)->toBe('Caso X');
        Queue::assertPushed(MonitorarAlvoJob::class, fn ($job) => $job->alvo->is($alvo));
    });

    it('cadastra OAB com UF', function () {
        $this->actingAs($this->user)
            ->post(route('monitoramento.alvos.store'), ['tipo' => 'oab', 'valor' => '12.345', 'uf' => 'am'])
            ->assertSessionHasNoErrors();

        expect($this->user->monitoringTargets()->sole()->only('valor', 'uf'))->toBe(['valor' => '12345', 'uf' => 'AM']);
    });

    it('valida o cadastro', function (array $dados, string $campo) {
        MonitoringTarget::factory()->for($this->user)->oab('999', 'SP')->create();

        $this->actingAs($this->user)
            ->post(route('monitoramento.alvos.store'), $dados)
            ->assertSessionHasErrors($campo);
    })->with([
        'tipo inválido' => [['tipo' => 'cpf', 'valor' => '1'], 'tipo'],
        'OAB sem UF' => [['tipo' => 'oab', 'valor' => '12345'], 'uf'],
        'OAB com letras' => [['tipo' => 'oab', 'valor' => 'abc', 'uf' => 'AM'], 'valor'],
        'processo incompleto' => [['tipo' => 'processo', 'valor' => '123'], 'valor'],
        'nome curto' => [['tipo' => 'parte', 'valor' => 'Jo'], 'valor'],
        'duplicado' => [['tipo' => 'oab', 'valor' => '999', 'uf' => 'SP'], 'valor'],
    ]);

    it('permite a mesma OAB em UFs diferentes', function () {
        MonitoringTarget::factory()->for($this->user)->oab('999', 'SP')->create();

        $this->actingAs($this->user)
            ->post(route('monitoramento.alvos.store'), ['tipo' => 'oab', 'valor' => '999', 'uf' => 'AM'])
            ->assertSessionHasNoErrors();
    });

    it('respeita o limite de alvos por usuário', function () {
        config(['djen.monitoring.max_alvos' => 1]);
        MonitoringTarget::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->post(route('monitoramento.alvos.store'), ['tipo' => 'parte', 'valor' => 'Joana Alves'])
            ->assertSessionHasErrors(['valor' => 'Você atingiu o limite de 1 alvos.']);
    });

    it('pausa, retoma e renomeia', function () {
        $alvo = MonitoringTarget::factory()->for($this->user)->create();

        $this->actingAs($this->user)->patch(route('monitoramento.alvos.update', $alvo), ['ativo' => false]);
        expect($alvo->fresh()->ativo)->toBeFalse();

        $this->patch(route('monitoramento.alvos.update', $alvo), ['apelido' => 'Novo']);
        expect($alvo->fresh()->only('ativo', 'apelido'))->toBe(['ativo' => false, 'apelido' => 'Novo']);
    });

    it('verifica agora só alvos ativos', function () {
        $ativo = MonitoringTarget::factory()->for($this->user)->create();
        $pausado = MonitoringTarget::factory()->for($this->user)->inativo()->create();

        $this->actingAs($this->user)->post(route('monitoramento.alvos.verificar', $ativo))->assertRedirect();
        $this->post(route('monitoramento.alvos.verificar', $pausado))->assertRedirect();

        Queue::assertPushed(MonitorarAlvoJob::class, 1);
    });

    it('impede mexer em alvos de outros usuários', function () {
        $alheio = MonitoringTarget::factory()->create();
        $this->actingAs($this->user);

        $this->patch(route('monitoramento.alvos.update', $alheio), ['ativo' => false])->assertForbidden();
        $this->delete(route('monitoramento.alvos.destroy', $alheio))->assertForbidden();
        $this->post(route('monitoramento.alvos.verificar', $alheio))->assertForbidden();

        expect($alheio->fresh())->not->toBeNull()->and($alheio->fresh()->ativo)->toBeTrue();
    });
});

describe('leitura', function () {
    it('marca como lida e não lida', function () {
        $alerta = alertaPara(MonitoringTarget::factory()->for($this->user)->create(), 'h1');

        $this->actingAs($this->user)->patch(route('monitoramento.alertas.update', $alerta), ['lida' => true])->assertRedirect();
        expect($alerta->fresh()->lida_em)->not->toBeNull();

        $this->patch(route('monitoramento.alertas.update', $alerta), ['lida' => false]);
        expect($alerta->fresh()->lida_em)->toBeNull();
    });

    it('marca todas como lidas, opcionalmente só de um alvo', function () {
        $a = MonitoringTarget::factory()->for($this->user)->create();
        $b = MonitoringTarget::factory()->for($this->user)->create();
        alertaPara($a, 'a1');
        alertaPara($b, 'b1');
        $alheio = alertaPara(MonitoringTarget::factory()->create(), 'x1');

        $this->actingAs($this->user)->post(route('monitoramento.alertas.marcar-lidas'), ['alvo' => $a->id]);
        expect($a->alertas()->whereNull('lida_em')->count())->toBe(0)
            ->and($b->alertas()->whereNull('lida_em')->count())->toBe(1);

        $this->post(route('monitoramento.alertas.marcar-lidas'));
        expect($b->alertas()->whereNull('lida_em')->count())->toBe(0)
            ->and($alheio->fresh()->lida_em)->toBeNull();
    });

    it('impede marcar alertas de outros usuários', function () {
        $alheio = alertaPara(MonitoringTarget::factory()->create(), 'x1');

        $this->actingAs($this->user)->patch(route('monitoramento.alertas.update', $alheio), ['lida' => true])->assertForbidden();
    });
});

describe('LGPD', function () {
    it('excluir um alvo apaga seus alertas e as comunicações órfãs, mas preserva as compartilhadas', function () {
        $meu = MonitoringTarget::factory()->for($this->user)->create();
        $deOutro = MonitoringTarget::factory()->create();
        alertaPara($meu, 'so-minha');
        alertaPara($meu, 'compartilhada');
        alertaPara($deOutro, 'compartilhada');

        $this->actingAs($this->user)->delete(route('monitoramento.alvos.destroy', $meu))
            ->assertRedirect(route('monitoramento.index'));

        expect(MonitoringTarget::find($meu->id))->toBeNull()
            ->and(Alerta::where('monitoring_target_id', $meu->id)->count())->toBe(0)
            ->and(Communication::pluck('hash')->all())->toBe(['compartilhada']);
    });

    it('exclui todos os dados de monitoramento com confirmação de senha', function () {
        $this->user->update(['whatsapp' => '5592999998888']);
        alertaPara(MonitoringTarget::factory()->for($this->user)->create(), 'h1');
        MonitoringTarget::factory()->for($this->user)->create();

        $this->actingAs($this->user)->delete(route('monitoramento.dados.destroy'), ['password' => 'errada'])
            ->assertSessionHasErrors(['password' => 'Senha incorreta.']);
        expect($this->user->monitoringTargets()->count())->toBe(2);

        $this->delete(route('monitoramento.dados.destroy'), ['password' => 'password'])
            ->assertRedirect(route('monitoramento.index'));

        expect($this->user->monitoringTargets()->count())->toBe(0)
            ->and(Communication::count())->toBe(0)
            ->and($this->user->fresh()->whatsapp)->toBeNull()
            ->and($this->user->fresh())->not->toBeNull();
    });

    it('excluir a conta apaga alvos e alertas (cascade); o prune limpa as órfãs', function () {
        alertaPara(MonitoringTarget::factory()->for($this->user)->create(), 'h1');

        $this->user->delete();

        expect(MonitoringTarget::count())->toBe(0)
            ->and(Alerta::count())->toBe(0);

        $this->artisan('model:prune', ['--model' => [Communication::class]])->assertSuccessful();
        expect(Communication::count())->toBe(0);
    });
});

describe('notificações', function () {
    it('salva o WhatsApp normalizado com DDI', function (string $entrada, string $salvo) {
        $this->actingAs($this->user)->put(route('monitoramento.notificacoes.update'), ['whatsapp' => $entrada])
            ->assertSessionHasNoErrors();

        expect($this->user->fresh()->whatsapp)->toBe($salvo);
    })->with([
        'celular com máscara' => ['(92) 99999-8888', '5592999998888'],
        'já com DDI' => ['+55 92 99999-8888', '5592999998888'],
        'fixo' => ['92 3333-4444', '559233334444'],
    ]);

    it('remove o WhatsApp quando vazio e valida números inválidos', function () {
        $this->user->update(['whatsapp' => '5592999998888']);
        $this->actingAs($this->user);

        $this->put(route('monitoramento.notificacoes.update'), ['whatsapp' => '123'])->assertSessionHasErrors('whatsapp');
        $this->put(route('monitoramento.notificacoes.update'), ['whatsapp' => ''])->assertSessionHasNoErrors();

        expect($this->user->fresh()->whatsapp)->toBeNull();
    });
});
