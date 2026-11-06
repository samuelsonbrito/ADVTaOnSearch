<?php

use App\Enums\StatusExportacao;
use App\Jobs\GerarExportacaoJob;
use App\Models\Alerta;
use App\Models\Communication;
use App\Models\Exportacao;
use App\Models\MonitoringTarget;
use App\Models\User;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenUnavailableException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\GatewayEmMemoria;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $this->gateway = new GatewayEmMemoria;
    foreach (range(1, 5) as $i) {
        $this->gateway->adicionar("h{$i}");
    }
    $this->app->instance(ComunicacaoGateway::class, $this->gateway);
    $this->user = User::factory()->create();
    config(['djen.itens_por_pagina' => 20, 'djen.exportacao.limite_sincrono' => 3]);
});

function pedidoConsulta(array $sobrescrever = []): array
{
    return array_merge([
        'origem' => 'consulta',
        'formato' => 'csv',
        'todos' => false,
        'filtros' => ['oab' => '12345', 'uf' => 'AM'],
        'selecao' => [['pagina' => 1, 'hashes' => ['h1', 'h2']]],
    ], $sobrescrever);
}

it('exige login', function () {
    $this->get(route('exportacoes.index'))->assertRedirect(route('login'));
    $this->post(route('exportacoes.store'), pedidoConsulta())->assertRedirect(route('login'));
});

it('gera seleções pequenas na hora e manda o navegador baixar', function () {
    $this->actingAs($this->user)
        ->post(route('exportacoes.store'), pedidoConsulta(), ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('exportacoes.download', Exportacao::sole()));

    $e = Exportacao::sole();
    expect($e->status)->toBe(StatusExportacao::Concluida)
        ->and($e->total_itens)->toBe(2)
        // jsonb não preserva a ordem das chaves.
        ->and($e->parametros)->toEqual([
            'filtros' => ['oab' => '12345', 'uf' => 'AM'],
            'todos' => false,
            'selecao' => [['pagina' => 1, 'hashes' => ['h1', 'h2']]],
        ]);
    Queue::assertNothingPushed();
});

it('manda seleções grandes para a fila', function () {
    $this->actingAs($this->user)
        ->post(route('exportacoes.store'), pedidoConsulta(['selecao' => [['pagina' => 1, 'hashes' => ['h1', 'h2', 'h3', 'h4']]]]))
        ->assertRedirect(route('exportacoes.index'));

    expect(Exportacao::sole()->status)->toBe(StatusExportacao::Pendente);
    Queue::assertPushed(GerarExportacaoJob::class, fn ($job) => $job->exportacao->is(Exportacao::sole()));
});

it('manda "exportar todas" sempre para a fila', function () {
    $this->actingAs($this->user)
        ->post(route('exportacoes.store'), pedidoConsulta(['todos' => true, 'selecao' => []]))
        ->assertRedirect(route('exportacoes.index'));

    expect(Exportacao::sole()->parametros['todos'])->toBeTrue();
    Queue::assertPushed(GerarExportacaoJob::class);
});

it('mostra erro amigável quando o CNJ falha numa exportação imediata', function () {
    $this->gateway->erro = new DjenUnavailableException('timeout');

    $this->actingAs($this->user)
        ->from(route('consulta.index'))
        ->post(route('exportacoes.store'), pedidoConsulta())
        ->assertRedirect(route('consulta.index'));

    expect(Exportacao::count())->toBe(0);
});

it('valida o pedido', function (array $pedido, string $campo) {
    $this->actingAs($this->user)
        ->post(route('exportacoes.store'), pedidoConsulta($pedido))
        ->assertSessionHasErrors($campo);
})->with([
    'formato' => [['formato' => 'docx'], 'formato'],
    'origem' => [['origem' => 'outra'], 'origem'],
    'seleção vazia' => [['selecao' => []], 'selecao'],
    'página inválida' => [['selecao' => [['pagina' => 0, 'hashes' => ['h1']]]], 'selecao.0.pagina'],
    'filtro inválido' => [['filtros' => ['processo' => '123']], 'filtros.processo'],
]);

it('limita o tamanho da seleção', function () {
    config(['djen.exportacao.max_itens' => 2]);

    $this->actingAs($this->user)
        ->post(route('exportacoes.store'), pedidoConsulta(['selecao' => [['pagina' => 1, 'hashes' => ['h1', 'h2', 'h3']]]]))
        ->assertSessionHasErrors('selecao');
});

it('exporta alertas do monitoramento e recusa alertas de outros usuários', function () {
    $meu = MonitoringTarget::factory()->for($this->user)->create();
    $alheio = MonitoringTarget::factory()->create();
    $c1 = Communication::create(Communication::atributosDe($this->gateway->comunicacoes[0]));
    $c2 = Communication::create(Communication::atributosDe($this->gateway->comunicacoes[1]));
    $a1 = Alerta::create(['communication_id' => $c1->id, 'monitoring_target_id' => $meu->id]);
    $a2 = Alerta::create(['communication_id' => $c2->id, 'monitoring_target_id' => $alheio->id]);
    $this->actingAs($this->user);

    $this->post(route('exportacoes.store'), [
        'origem' => 'monitoramento', 'formato' => 'xlsx', 'todos' => false,
        'filtros' => ['alvo' => null, 'status' => 'todas'], 'selecao' => [$a1->id, $a2->id],
    ])->assertSessionHasErrors(['selecao' => 'Seleção inválida.']);

    $this->post(route('exportacoes.store'), [
        'origem' => 'monitoramento', 'formato' => 'xlsx', 'todos' => false,
        'filtros' => ['alvo' => null, 'status' => 'todas'], 'selecao' => [$a1->id],
    ], ['X-Inertia' => 'true'])->assertStatus(409);

    expect(Exportacao::sole()->total_itens)->toBe(1);

    $this->post(route('exportacoes.store'), [
        'origem' => 'monitoramento', 'formato' => 'csv', 'todos' => true,
        'filtros' => ['alvo' => $alheio->id, 'status' => 'todas'], 'selecao' => [],
    ])->assertSessionHasErrors('filtros.alvo');
});

it('lista as exportações do usuário e sinaliza as em andamento', function () {
    $pronta = $this->user->exportacoes()->create(['origem' => 'consulta', 'formato' => 'pdf', 'parametros' => ['todos' => false], 'status' => 'concluida', 'total_itens' => 3, 'arquivo' => 'x.pdf']);
    $this->user->exportacoes()->create(['origem' => 'monitoramento', 'formato' => 'csv', 'parametros' => ['todos' => true]]);
    User::factory()->create()->exportacoes()->create(['origem' => 'consulta', 'formato' => 'csv', 'parametros' => []]);

    $this->actingAs($this->user)->get(route('exportacoes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('Exportacao/Index')
            ->has('exportacoes', 2)
            ->where('emAndamento', true)
            ->where('diasRetencao', 7)
            ->has('formatos', 3)
            ->where('exportacoes.0.status', 'pendente')
            ->where('exportacoes.0.statusRotulo', 'Na fila')
            ->where('exportacoes.0.todos', true)
            ->where('exportacoes.0.downloadUrl', null)
            ->has('exportacoes.1', fn (Assert $e) => $e
                ->where('id', $pronta->id)
                ->where('formato', 'pdf')
                ->where('formatoRotulo', 'PDF')
                ->where('origemRotulo', 'Consulta')
                ->where('totalItens', 3)
                ->where('downloadUrl', route('exportacoes.download', $pronta))
                ->etc()));
});

it('baixa o arquivo com nome amigável, só para o dono', function () {
    $this->actingAs($this->user)->post(route('exportacoes.store'), pedidoConsulta(), ['X-Inertia' => 'true']);
    $e = Exportacao::sole();

    $this->get(route('exportacoes.download', $e))
        ->assertOk()
        ->assertDownload($e->nomeDownload())
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($e->nomeDownload())->toMatch('/^comunicacoes-consulta-\d{4}-\d{2}-\d{2}-\d{4}\.csv$/');

    $this->actingAs(User::factory()->create())->get(route('exportacoes.download', $e))->assertForbidden();
});

it('responde 404 para arquivo ainda não pronto ou expirado', function () {
    $e = $this->user->exportacoes()->create(['origem' => 'consulta', 'formato' => 'csv', 'parametros' => []]);

    $this->actingAs($this->user)->get(route('exportacoes.download', $e))->assertNotFound();
});

it('exclui exportação e arquivo (LGPD), só do dono', function () {
    $this->actingAs($this->user)->post(route('exportacoes.store'), pedidoConsulta(), ['X-Inertia' => 'true']);
    $e = Exportacao::sole();

    $this->actingAs(User::factory()->create())->delete(route('exportacoes.destroy', $e))->assertForbidden();

    $this->actingAs($this->user)->delete(route('exportacoes.destroy', $e))->assertRedirect(route('exportacoes.index'));
    expect(Exportacao::count())->toBe(0);
    Storage::disk('local')->assertMissing($e->arquivo);
});
