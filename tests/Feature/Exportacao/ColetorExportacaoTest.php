<?php

use App\Models\Alerta;
use App\Models\Communication;
use App\Models\Exportacao;
use App\Models\MonitoringTarget;
use App\Models\User;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Exportacao\ColetorExportacao;
use App\Services\Exportacao\LinhaExportacao;
use Tests\Support\GatewayEmMemoria;

beforeEach(function () {
    $this->gateway = new GatewayEmMemoria;
    $this->app->instance(ComunicacaoGateway::class, $this->gateway);
    $this->user = User::factory()->create();
    config(['djen.itens_por_pagina' => 3]);
});

function exportacao(array $atributos): Exportacao
{
    return test()->user->exportacoes()->create(array_merge(['formato' => 'csv'], $atributos));
}

function hashes(array $linhas): array
{
    return array_map(fn (LinhaExportacao $l) => $l->comunicacao->hash, $linhas);
}

describe('consulta', function () {
    beforeEach(function () {
        foreach (range(1, 7) as $i) {
            $this->gateway->adicionar("h{$i}");
        }
    });

    it('busca cada página selecionada com os filtros salvos e mantém só os hashes escolhidos', function () {
        $e = exportacao(['origem' => 'consulta', 'parametros' => [
            'filtros' => ['oab' => '12345', 'uf' => 'AM', 'inicio' => '2026-11-01'],
            'todos' => false,
            'selecao' => [['pagina' => 1, 'hashes' => ['h3', 'h1']], ['pagina' => 3, 'hashes' => ['h7', 'sumiu']]],
        ]]);

        $linhas = app(ColetorExportacao::class)->linhas($e);

        expect(hashes($linhas))->toBe(['h1', 'h3', 'h7']) // ordem do resultado; hash ausente é ignorado
            ->and(array_map(fn ($f) => $f->pagina, $this->gateway->filtros))->toBe([1, 3])
            ->and($this->gateway->filtros[0]->numeroOab)->toBe('12345')
            ->and($this->gateway->filtros[0]->ufOab)->toBe('AM')
            ->and($this->gateway->filtros[0]->dataInicio->toDateString())->toBe('2026-11-01')
            ->and($this->gateway->filtros[0]->porPagina)->toBe(3);
    });

    it('exporta todos os resultados percorrendo as páginas', function () {
        $e = exportacao(['origem' => 'consulta', 'parametros' => ['filtros' => ['tribunal' => 'TJAM'], 'todos' => true, 'selecao' => []]]);

        expect(app(ColetorExportacao::class)->linhas($e))->toHaveCount(7);
    });

    it('respeita o máximo de itens', function () {
        config(['djen.exportacao.max_itens' => 5]);
        $e = exportacao(['origem' => 'consulta', 'parametros' => ['filtros' => ['tribunal' => 'TJAM'], 'todos' => true, 'selecao' => []]]);

        expect(app(ColetorExportacao::class)->linhas($e))->toHaveCount(5);
    });
});

describe('monitoramento', function () {
    beforeEach(function () {
        $this->alvoA = MonitoringTarget::factory()->for($this->user)->oab('111', 'AM')->create();
        $this->alvoB = MonitoringTarget::factory()->for($this->user)->oab('222', 'AM')->create();
        $this->alertas = [];

        foreach ([['a1', $this->alvoA, '2026-11-01', false], ['a2', $this->alvoA, '2026-11-05', true], ['b1', $this->alvoB, '2026-11-03', false]] as [$hash, $alvo, $data, $lida]) {
            $c = Communication::create(Communication::atributosDe($this->gateway->adicionar($hash, $data)));
            $this->alertas[$hash] = Alerta::create(['communication_id' => $c->id, 'monitoring_target_id' => $alvo->id, 'lida_em' => $lida ? now() : null]);
        }

        $alheio = MonitoringTarget::factory()->create();
        $c = Communication::create(Communication::atributosDe($this->gateway->adicionar('x1')));
        $this->alheio = Alerta::create(['communication_id' => $c->id, 'monitoring_target_id' => $alheio->id]);
    });

    it('exporta os alertas selecionados com alvo e status de leitura, mais recentes primeiro', function () {
        $e = exportacao(['origem' => 'monitoramento', 'parametros' => [
            'filtros' => [], 'todos' => false, 'selecao' => [$this->alertas['a1']->id, $this->alertas['a2']->id],
        ]]);

        $linhas = app(ColetorExportacao::class)->linhas($e);

        expect(hashes($linhas))->toBe(['a2', 'a1'])
            ->and($linhas[0]->alvo)->toBe('OAB 111/AM')
            ->and($linhas[0]->lida)->toBeTrue()
            ->and($linhas[1]->lida)->toBeFalse();
    });

    it('nunca inclui alertas de outros usuários, mesmo se o id vier nos parâmetros', function () {
        $e = exportacao(['origem' => 'monitoramento', 'parametros' => [
            'filtros' => [], 'todos' => false, 'selecao' => [$this->alertas['b1']->id, $this->alheio->id],
        ]]);

        expect(hashes(app(ColetorExportacao::class)->linhas($e)))->toBe(['b1']);
    });

    it('exporta todos com os filtros de alvo e status', function (array $filtros, array $esperado) {
        $e = exportacao(['origem' => 'monitoramento', 'parametros' => ['filtros' => $filtros, 'todos' => true, 'selecao' => []]]);

        expect(hashes(app(ColetorExportacao::class)->linhas($e)))->toBe($esperado);
    })->with([
        'todas' => [['status' => 'todas'], ['a2', 'b1', 'a1']],
        'não lidas' => [['status' => 'nao_lidas'], ['b1', 'a1']],
        'lidas' => [['status' => 'lidas'], ['a2']],
        'por alvo' => [fn () => ['alvo' => $this->alvoA->id, 'status' => 'todas'], ['a2', 'a1']],
    ]);
});
