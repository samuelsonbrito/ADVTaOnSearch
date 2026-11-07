<?php

use App\Models\Atividade;
use App\Models\ChamadaDjen;
use App\Models\RegistroBusca;
use App\Models\User;
use App\Support\Rastreio\TipoEvento;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['djen.monitoring.timezone' => 'America/Manaus']);
    // "Agora": 07/11/2026 10:00 em Manaus.
    $this->travelTo(CarbonImmutable::parse('2026-11-07 14:00:00', 'UTC'));
    $this->admin = User::factory()->admin()->create();
});

/** Uma busca em horário local de Manaus. */
function busca(string $local, array $attrs = []): RegistroBusca
{
    return RegistroBusca::create([
        'visitante' => 'v1', 'dispositivo' => 'desktop', 'criterio' => 'oab', 'oab' => '12345', 'uf' => 'AM',
        'status' => 'ok', 'total_resultados' => 3, 'duracao_ms' => 100, 'do_cache' => false,
        'created_at' => CarbonImmutable::parse($local, 'America/Manaus')->utc(),
        ...$attrs,
    ]);
}

it('mostra indicadores, séries por dia e por hora no fuso local, sem robôs', function () {
    busca('2026-11-07 09:00');
    busca('2026-11-07 09:30', ['visitante' => 'v2', 'status' => 'vazio', 'total_resultados' => 0, 'duracao_ms' => 300, 'do_cache' => true]);
    busca('2026-11-06 23:30', ['visitante' => 'v1', 'criterio' => 'parte', 'parte' => 'Joana', 'oab' => null, 'uf' => null]); // 03:30 UTC do dia 07
    busca('2026-11-07 08:00', ['bot' => true, 'visitante' => 'robo']);
    busca('2026-10-30 12:00'); // período anterior
    Atividade::create(['tipo' => TipoEvento::PAGINA_VISTA, 'visitante' => 'v3', 'dispositivo' => 'celular', 'caminho' => '/consulta', 'created_at' => now()]);
    ChamadaDjen::create(['origem' => 'consulta', 'endpoint' => 'comunicacao', 'parametros' => [], 'status' => 200, 'resultado' => 'sucesso', 'duracao_ms' => 200, 'ratelimit_restante' => 15, 'created_at' => now()]);
    ChamadaDjen::create(['origem' => 'monitoramento', 'endpoint' => 'comunicacao', 'parametros' => [], 'status' => 503, 'resultado' => 'indisponivel', 'duracao_ms' => 400, 'created_at' => now()]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['periodo' => '7d']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('periodo', ['chave' => '7d', 'inicio' => '2026-11-01', 'fim' => '2026-11-07', 'dias' => 7])
            ->where('indicadores.buscas', ['valor' => 3, 'anterior' => 1])
            ->where('indicadores.visitantes.valor', 3) // v1, v2 (buscas) e v3 (página)
            ->where('indicadores.taxaResultado.valor', fn ($v) => abs($v - 66.7) < 0.01)
            ->where('indicadores.taxaCache.valor', fn ($v) => abs($v - 33.3) < 0.01)
            ->where('indicadores.tempoMedioMs.valor', 167)
            ->where('indicadores.chamadasCnj.valor', 2)
            ->where('indicadores.falhasCnj.valor', fn ($v) => (float) $v === 50.0)
            ->where('indicadores.cadastros.valor', 1)
            ->has('porDia', 7)
            ->where('porDia.5', ['data' => '2026-11-06', 'buscas' => 1, 'visitantes' => 1])
            ->where('porDia.6', ['data' => '2026-11-07', 'buscas' => 2, 'visitantes' => 3])
            ->has('buscasPorHora', 24)
            ->where('buscasPorHora.9.total', 2)
            ->where('buscasPorHora.23.total', 1)
            ->where('buscasPorCriterio.0', ['chave' => 'oab', 'rotulo' => 'OAB', 'total' => 2])
            ->where('termos.parte.0', ['chave' => 'JOANA', 'rotulo' => 'JOANA', 'total' => 1])
            ->where('termos.oab.0.rotulo', '12345/AM')
            ->where('ufs.0', ['chave' => 'AM', 'rotulo' => 'AM', 'total' => 2])
            ->where('dispositivos.0.rotulo', 'Celular')
            ->where('cnj.latenciaMediaMs', 300)
            ->where('cnj.ratelimitRestante', 15)
            ->where('cnj.porOrigem', fn ($o) => collect($o)->pluck('chave')->sort()->values()->all() === ['consulta', 'monitoramento']));
});

it('lista e filtra as buscas', function () {
    $user = User::factory()->create(['name' => 'Ana']);
    busca('2026-11-07 09:00', ['user_id' => $user->id]);
    busca('2026-11-07 09:10', ['criterio' => 'parte', 'parte' => 'Joana Ferreira', 'oab' => null, 'uf' => null]);
    busca('2026-11-07 09:20', ['bot' => true]);

    $this->actingAs($this->admin)->get(route('admin.buscas'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('buscas.meta.total', 2)
            ->where('buscas.data.0.filtros', ['Parte' => 'Joana Ferreira'])
            ->where('buscas.data.1.usuario.nome', 'Ana')
            ->where('buscas.data.1.filtros', ['OAB' => '12345/AM']));

    $this->get(route('admin.buscas', ['termo' => 'ferreira']))->assertInertia(fn (Assert $p) => $p->where('buscas.meta.total', 1));
    $this->get(route('admin.buscas', ['quem' => 'usuarios']))->assertInertia(fn (Assert $p) => $p->where('buscas.meta.total', 1));
    $this->get(route('admin.buscas', ['criterio' => 'oab', 'robos' => 1]))->assertInertia(fn (Assert $p) => $p->where('buscas.meta.total', 2));
});

it('lista a atividade com contagem por tipo e filtro por usuário', function () {
    $user = User::factory()->create();
    Atividade::create(['tipo' => TipoEvento::USUARIO_LOGIN, 'user_id' => $user->id, 'created_at' => now()]);
    Atividade::create(['tipo' => TipoEvento::ALVO_CRIADO, 'user_id' => $user->id, 'dados' => ['alvo_id' => 1], 'created_at' => now()]);
    Atividade::create(['tipo' => TipoEvento::USUARIO_LOGIN, 'created_at' => now()]);

    $this->actingAs($this->admin)->get(route('admin.atividade', ['usuario' => $user->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('eventos.meta.total', 2)
            ->where('filtros.usuario.id', $user->id)
            ->where('porTipo', [
                ['chave' => 'alvo.criado', 'rotulo' => 'Alvo criado', 'total' => 1],
                ['chave' => 'usuario.login', 'rotulo' => 'Login', 'total' => 1],
            ])
            ->where('eventos.data.0.rotulo', 'Alvo criado'));
});

it('lista usuários com uso e último acesso', function () {
    $user = User::factory()->create(['name' => 'Bruno']);
    $user->monitoringTargets()->create(['tipo' => 'oab', 'valor' => '1', 'uf' => 'AM']);
    busca('2026-11-07 09:00', ['user_id' => $user->id]);
    Atividade::create(['tipo' => TipoEvento::USUARIO_LOGIN, 'user_id' => $user->id, 'created_at' => now()]);

    $this->actingAs($this->admin)->get(route('admin.usuarios', ['q' => 'brun']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('usuarios.meta.total', 1)
            ->where('usuarios.data.0', fn ($u) => $u['nome'] === 'Bruno' && $u['alvos'] === 1 && $u['buscas'] === 1
                && str_starts_with($u['ultimoAcesso'], '2026-11-07T10:00'))
            ->where('totais.usuarios', 2)
            ->where('totais.admins', 1));
});

it('lista as chamadas ao CNJ com filtros', function () {
    ChamadaDjen::create(['origem' => 'consulta', 'endpoint' => 'comunicacao', 'parametros' => ['numeroOab' => '1'], 'status' => 200, 'resultado' => 'sucesso', 'duracao_ms' => 120, 'created_at' => now()]);
    ChamadaDjen::create(['origem' => 'monitoramento', 'endpoint' => 'comunicacao', 'parametros' => [], 'status' => 403, 'resultado' => 'bloqueado', 'duracao_ms' => 80, 'erro' => '403', 'created_at' => now()]);

    $this->actingAs($this->admin)->get(route('admin.cnj', ['resultado' => 'bloqueado']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('chamadas.meta.total', 1)
            ->where('chamadas.data.0.origem', 'monitoramento')
            ->where('resumo.total', 2));
});

it('apaga o rastreio antigo no prune (LGPD)', function () {
    config(['djen.rastreio.dias_retencao' => 30]);
    busca('2026-09-01 10:00');
    busca('2026-11-07 09:00');
    Atividade::create(['tipo' => TipoEvento::USUARIO_LOGIN, 'created_at' => now()->subDays(31)]);
    ChamadaDjen::create(['origem' => 'outro', 'endpoint' => 'x', 'parametros' => [], 'resultado' => 'sucesso', 'duracao_ms' => 1, 'created_at' => now()->subDays(40)]);

    $this->artisan('model:prune', ['--model' => [RegistroBusca::class, Atividade::class, ChamadaDjen::class]])->assertSuccessful();

    expect(RegistroBusca::count())->toBe(1)->and(Atividade::count())->toBe(0)->and(ChamadaDjen::count())->toBe(0);
});
