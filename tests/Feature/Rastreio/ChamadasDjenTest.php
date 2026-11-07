<?php

use App\Data\Djen\FiltroConsulta;
use App\Models\ChamadaDjen;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\DjenClient;
use App\Services\Djen\Exceptions\DjenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

const API_CNJ = 'https://comunicaapi.pje.jus.br/api/v1';

beforeEach(function () {
    config(['djen.gateway' => 'api', 'djen.base_url' => API_CNJ, 'djen.retry.times' => 0]);
    app()->forgetInstance(DjenClient::class);
    app()->forgetInstance(ComunicacaoGateway::class);
    Http::preventStrayRequests();
    Sleep::fake();
    RateLimiter::clear('djen-consulta');
    $this->fixture = json_decode(file_get_contents(base_path('tests/Fixtures/djen/comunicacao.json')), true);
});

it('registra cada chamada real ao CNJ com origem, tempo, itens e limite restante', function () {
    Http::fake([API_CNJ.'/comunicacao*' => Http::response($this->fixture, 200, ['x-ratelimit-remaining' => '17'])]);

    $this->get(route('consulta.index', ['oab' => '12345', 'uf' => 'AM']))->assertOk();

    $c = ChamadaDjen::sole();

    expect($c->only(['origem', 'endpoint', 'status', 'resultado', 'itens', 'ratelimit_restante', 'erro']))->toBe([
        'origem' => 'consulta', 'endpoint' => 'comunicacao', 'status' => 200, 'resultado' => 'sucesso',
        'itens' => count($this->fixture['items']), 'ratelimit_restante' => 17, 'erro' => null,
    ])
        ->and($c->parametros)->toMatchArray(['numeroOab' => '12345', 'ufOab' => 'AM'])
        ->and($c->duracao_ms)->toBeInt();
});

it('não registra respostas vindas do cache', function () {
    Http::fake([API_CNJ.'/comunicacao*' => Http::response($this->fixture)]);
    $filtro = new FiltroConsulta(numeroOab: '12345', ufOab: 'AM');

    app(ComunicacaoGateway::class)->buscar($filtro);
    $segunda = app(ComunicacaoGateway::class)->buscar($filtro);

    expect(ChamadaDjen::count())->toBe(1)
        ->and($segunda->doCache)->toBeTrue();
});

it('registra as falhas com o resultado certo', function (callable $resposta, string $resultado, ?int $status) {
    Http::fake([API_CNJ.'/comunicacao*' => $resposta]);

    expect(fn () => app(DjenClient::class)->get('comunicacao', ['pagina' => 1]))->toThrow(DjenException::class);

    expect(ChamadaDjen::sole()->only(['origem', 'resultado', 'status']))
        ->toBe(['origem' => 'outro', 'resultado' => $resultado, 'status' => $status])
        ->and(ChamadaDjen::sole()->erro)->not->toBeNull();
})->with([
    'bloqueado' => [fn () => Http::response('Forbidden', 403), 'bloqueado', 403],
    'limite' => [fn () => Http::response('', 429, ['Retry-After' => '30']), 'limitado', 429],
    'fora do ar' => [fn () => Http::response('', 503), 'indisponivel', 503],
    'recusado' => [fn () => Http::response('Bad', 400), 'recusado', 400],
    'não é JSON' => [fn () => Http::response('<html>', 200), 'invalido', 200],
    'sem conexão' => [fn () => throw new ConnectionException('timeout'), 'indisponivel', null],
]);
