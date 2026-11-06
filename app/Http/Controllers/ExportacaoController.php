<?php

namespace App\Http\Controllers;

use App\Enums\FormatoExportacao;
use App\Http\Requests\Exportacao\StoreExportacaoRequest;
use App\Http\Resources\Exportacao\ExportacaoResource;
use App\Jobs\GerarExportacaoJob;
use App\Models\Exportacao;
use App\Services\Djen\Exceptions\DjenException;
use App\Services\Exportacao\GeradorExportacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Aba Exportação: gera CSV/XLSX/PDF a partir da Consulta ou do Monitoramento. */
class ExportacaoController extends Controller
{
    public function index(Request $request): Response
    {
        $exportacoes = $request->user()->exportacoes()->latest('id')->limit(50)->get();

        return Inertia::render('Exportacao/Index', [
            'exportacoes' => ExportacaoResource::collection($exportacoes)->resolve(),
            'emAndamento' => $exportacoes->contains(fn (Exportacao $e) => $e->status->emAndamento()),
            'diasRetencao' => (int) config('djen.exportacao.dias_retencao'),
            'formatos' => array_map(fn (FormatoExportacao $f) => ['valor' => $f->value, 'rotulo' => $f->rotulo()], FormatoExportacao::cases()),
        ]);
    }

    /**
     * Seleções pequenas: gera na hora e inicia o download.
     * Grandes ou "todos os resultados": fila + e-mail quando ficar pronto.
     */
    public function store(StoreExportacaoRequest $request, GeradorExportacao $gerador): HttpResponse
    {
        $exportacao = $request->user()->exportacoes()->create([
            'origem' => $request->validated('origem'),
            'formato' => $request->validated('formato'),
            'parametros' => $request->parametros(),
        ]);

        $sincrona = ! $request->boolean('todos')
            && $request->quantidadeSelecionada() <= (int) config('djen.exportacao.limite_sincrono');

        if (! $sincrona) {
            GerarExportacaoJob::dispatch($exportacao);

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Exportação na fila. Avisaremos por e-mail quando o arquivo estiver pronto.']);

            return to_route('exportacoes.index');
        }

        try {
            $gerador->gerar($exportacao);
        } catch (DjenException) {
            $exportacao->delete();
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Não foi possível obter os dados do CNJ agora. Tente novamente em instantes.']);

            return back();
        }

        // Visita completa: o navegador baixa o arquivo e a página atual continua.
        return Inertia::location(route('exportacoes.download', $exportacao));
    }

    public function download(Request $request, Exportacao $exportacao): StreamedResponse
    {
        Gate::authorize('view', $exportacao);

        abort_unless($exportacao->disponivel(), 404, 'Arquivo indisponível ou expirado.');

        return Storage::disk(Exportacao::disco())->download(
            $exportacao->arquivo,
            $exportacao->nomeDownload(),
            ['Content-Type' => $exportacao->formato->mime()],
        );
    }

    public function destroy(Request $request, Exportacao $exportacao): RedirectResponse
    {
        Gate::authorize('delete', $exportacao);

        $exportacao->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Exportação e arquivo excluídos.']);

        return to_route('exportacoes.index');
    }
}
