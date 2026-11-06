<?php

use App\Enums\StatusExportacao;
use App\Jobs\GerarExportacaoJob;
use App\Models\Exportacao;
use App\Models\User;
use App\Notifications\ExportacaoConcluida;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenUnavailableException;
use App\Services\Exportacao\GeradorExportacao;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GatewayEmMemoria;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->gateway = new GatewayEmMemoria;
    $this->gateway->adicionar('h1');
    $this->app->instance(ComunicacaoGateway::class, $this->gateway);
    $this->exportacao = User::factory()->create()->exportacoes()->create([
        'origem' => 'consulta', 'formato' => 'xlsx',
        'parametros' => ['filtros' => ['tribunal' => 'TJAM'], 'todos' => true, 'selecao' => []],
    ]);
});

it('gera o arquivo e avisa o usuário por e-mail', function () {
    GerarExportacaoJob::dispatchSync($this->exportacao);

    $e = $this->exportacao->fresh();
    expect($e->status)->toBe(StatusExportacao::Concluida)->and($e->total_itens)->toBe(1);
    Storage::disk('local')->assertExists($e->arquivo);

    Notification::assertSentTo($e->user, ExportacaoConcluida::class, function (ExportacaoConcluida $n) use ($e) {
        $mail = $n->toMail($e->user);

        return $mail->subject === 'Sua exportação está pronta (Excel (XLSX))'
            && $mail->actionUrl === route('exportacoes.download', $e)
            && str_contains(implode(' ', $mail->introLines), 'com 1 comunicação (consulta)');
    });
});

it('marca como falha com mensagem amigável quando o job desiste', function () {
    $this->gateway->erro = new DjenUnavailableException('timeout');
    $job = new GerarExportacaoJob($this->exportacao);

    expect(fn () => $job->handle(app(GeradorExportacao::class)))->toThrow(DjenUnavailableException::class);
    $job->failed(new DjenUnavailableException('timeout'));

    expect($this->exportacao->fresh()->status)->toBe(StatusExportacao::Falhou)
        ->and($this->exportacao->fresh()->erro)->toBe('Não foi possível obter os dados do CNJ. Tente novamente mais tarde.');
    Notification::assertNothingSent();
});

it('aplica o rate limit do CNJ só às exportações da consulta', function () {
    expect((new GerarExportacaoJob($this->exportacao))->middleware()[0])->toBeInstanceOf(RateLimited::class);

    $this->exportacao->update(['origem' => 'monitoramento']);
    expect((new GerarExportacaoJob($this->exportacao))->middleware())->toBe([]);
});

it('LGPD: o prune apaga exportações expiradas e seus arquivos', function () {
    GerarExportacaoJob::dispatchSync($this->exportacao);
    $antiga = $this->exportacao->fresh();
    $nova = $antiga->user->exportacoes()->create(['origem' => 'consulta', 'formato' => 'csv', 'parametros' => []]);
    $antiga->forceFill(['created_at' => now()->subDays(8)])->save();

    $this->artisan('model:prune', ['--model' => [Exportacao::class]])->assertSuccessful();

    expect(Exportacao::pluck('id')->all())->toBe([$nova->id]);
    Storage::disk('local')->assertMissing($antiga->arquivo);
});

it('LGPD: excluir a conta apaga exportações e arquivos', function () {
    GerarExportacaoJob::dispatchSync($this->exportacao);
    $arquivo = $this->exportacao->fresh()->arquivo;

    $this->exportacao->user->delete();

    expect(Exportacao::count())->toBe(0);
    Storage::disk('local')->assertMissing($arquivo);
});
