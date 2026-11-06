<?php

namespace App\Jobs;

use App\Enums\OrigemExportacao;
use App\Enums\StatusExportacao;
use App\Models\Exportacao;
use App\Notifications\ExportacaoConcluida;
use App\Services\Djen\Exceptions\DjenException;
use App\Services\Exportacao\GeradorExportacao;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/** Exportações grandes: gera o arquivo na fila e avisa o usuário por e-mail. */
class GerarExportacaoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 600;

    public function __construct(
        public Exportacao $exportacao,
    ) {}

    /** @return list<object> */
    public function middleware(): array
    {
        // Exportações da Consulta chamam o CNJ.
        return $this->exportacao->origem === OrigemExportacao::Consulta
            ? [new RateLimited('djen')]
            : [];
    }

    public function handle(GeradorExportacao $gerador): void
    {
        $gerador->gerar($this->exportacao);

        $this->exportacao->user->notify(new ExportacaoConcluida($this->exportacao->fresh()));
    }

    public function failed(?Throwable $e): void
    {
        $this->exportacao->update([
            'status' => StatusExportacao::Falhou,
            'erro' => $e instanceof DjenException
                ? 'Não foi possível obter os dados do CNJ. Tente novamente mais tarde.'
                : 'Erro inesperado ao gerar o arquivo.',
        ]);
    }
}
