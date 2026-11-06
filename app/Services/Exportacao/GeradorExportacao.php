<?php

namespace App\Services\Exportacao;

use App\Enums\FormatoExportacao;
use App\Enums\OrigemExportacao;
use App\Enums\StatusExportacao;
use App\Models\Exportacao;
use App\Services\Djen\Exceptions\DjenException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

/** Gera o arquivo de uma exportação e atualiza o registro. */
final class GeradorExportacao
{
    public function __construct(
        private readonly ColetorExportacao $coletor,
    ) {}

    /**
     * @throws DjenException
     */
    public function gerar(Exportacao $exportacao): void
    {
        $exportacao->update(['status' => StatusExportacao::Processando, 'erro' => null]);

        $linhas = $this->coletor->linhas($exportacao);
        $caminho = "exportacoes/{$exportacao->user_id}/{$exportacao->id}-".bin2hex(random_bytes(8)).".{$exportacao->formato->value}";

        Storage::disk(Exportacao::disco())->put($caminho, $this->conteudo($exportacao, $linhas));

        $exportacao->apagarArquivo(); // regeração: remove o arquivo anterior, se houver
        $exportacao->update([
            'status' => StatusExportacao::Concluida,
            'arquivo' => $caminho,
            'total_itens' => count($linhas),
            'concluida_em' => now(),
        ]);
    }

    /**
     * @param  list<LinhaExportacao>  $linhas
     */
    private function conteudo(Exportacao $e, array $linhas): string
    {
        $comAlvo = $e->origem === OrigemExportacao::Monitoramento;

        return match ($e->formato) {
            FormatoExportacao::Csv => ExcelFacade::raw(new PlanilhaComunicacoes($linhas, $comAlvo), Excel::CSV),
            FormatoExportacao::Xlsx => ExcelFacade::raw(new PlanilhaComunicacoes($linhas, $comAlvo), Excel::XLSX),
            FormatoExportacao::Pdf => Pdf::loadView('exports.comunicacoes', [
                'linhas' => $linhas,
                'comAlvo' => $comAlvo,
                'origem' => $e->origem->rotulo(),
                'geradaEm' => now()->timezone((string) config('djen.monitoring.timezone')),
            ])
                ->setPaper('a4')
                // Embute só os glifos usados: sem isso a fonte inteira vai no arquivo (~1 MB).
                ->setOption('enable_font_subsetting', true)
                ->output(),
        };
    }
}
