<?php

use App\Data\Djen\AdvogadoData;
use App\Data\Djen\ComunicacaoData;
use App\Data\Djen\DestinatarioData;
use App\Enums\StatusExportacao;
use App\Models\Exportacao;
use App\Models\User;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Exportacao\GeradorExportacao;
use App\Services\Exportacao\LinhaExportacao;
use App\Services\Exportacao\PlanilhaComunicacoes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\GatewayEmMemoria;

beforeEach(function () {
    Storage::fake('local');
    $this->gateway = new GatewayEmMemoria;
    $this->gateway->comunicacoes[] = new ComunicacaoData(
        hash: 'h1',
        tribunal: 'TJAM',
        orgaoJulgador: '1ª Vara Cível',
        tipoComunicacao: 'Intimação',
        tipoDocumento: 'Despacho',
        dataDisponibilizacao: CarbonImmutable::parse('2026-11-06'),
        numeroProcesso: '00012345620248040001',
        destinatarios: [new DestinatarioData('José Ação'), new DestinatarioData('=HYPERLINK("http://mal")')],
        advogados: [new AdvogadoData('Helena Prado', '12345', 'AM')],
        textoHtml: '<p>Fica <strong>intimada</strong> &amp; ciente.</p><script>x</script>',
        linkDocumento: 'https://example.org/doc',
    );
    $this->app->instance(ComunicacaoGateway::class, $this->gateway);
});

function gerar(string $formato, string $origem = 'consulta'): Exportacao
{
    $e = User::factory()->create()->exportacoes()->create([
        'origem' => $origem,
        'formato' => $formato,
        'parametros' => ['filtros' => ['tribunal' => 'TJAM'], 'todos' => true, 'selecao' => []],
    ]);

    app(GeradorExportacao::class)->gerar($e);

    return $e->fresh();
}

it('gera CSV com BOM, ponto e vírgula, cabeçalho e dados formatados', function () {
    $e = gerar('csv');
    $csv = Storage::disk('local')->get($e->arquivo);

    expect($e->status)->toBe(StatusExportacao::Concluida)
        ->and($e->total_itens)->toBe(1)
        ->and($e->concluida_em)->not->toBeNull()
        ->and($e->arquivo)->toStartWith("exportacoes/{$e->user_id}/")->toEndWith('.csv')
        ->and($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('"Data de disponibilização";"Tribunal";"Órgão julgador"')
        ->and($csv)->toContain('"06/11/2026";"TJAM";"1ª Vara Cível";"Intimação";"Despacho";"0001234-56.2024.8.04.0001"')
        ->and($csv)->toContain('"Helena Prado (OAB 12345/AM)"')
        ->and($csv)->toContain('"Fica intimada & ciente."')
        ->and($csv)->not->toContain('<script>');
});

it('neutraliza injeção de fórmula nas células', function () {
    expect(PlanilhaComunicacoes::celulaSegura('=1+1'))->toBe("'=1+1")
        ->and(PlanilhaComunicacoes::celulaSegura('+55'))->toBe("'+55")
        ->and(PlanilhaComunicacoes::celulaSegura('-2'))->toBe("'-2")
        ->and(PlanilhaComunicacoes::celulaSegura('@SUM'))->toBe("'@SUM")
        ->and(PlanilhaComunicacoes::celulaSegura('0001234-56'))->toBe('0001234-56')
        ->and(PlanilhaComunicacoes::celulaSegura('José'))->toBe('José');

    // Só a célula inteira que começa com "=" é neutralizada (aqui é o 2º destinatário, no meio da célula).
    $planilha = new PlanilhaComunicacoes([new LinhaExportacao($this->gateway->comunicacoes[0])]);
    expect($planilha->array()[0][6])->toBe('José Ação; =HYPERLINK("http://mal")');
});

it('gera XLSX legível com cabeçalho e colunas de alvo no monitoramento', function () {
    $e = gerar('xlsx');
    $arquivo = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($arquivo, Storage::disk('local')->get($e->arquivo));

    $planilha = IOFactory::load($arquivo)->getActiveSheet();

    expect($planilha->getTitle())->toBe('Comunicações')
        ->and($planilha->getCell('A1')->getValue())->toBe('Data de disponibilização')
        ->and($planilha->getCell('B2')->getValue())->toBe('TJAM')
        ->and($planilha->getCell('F2')->getValue())->toBe('0001234-56.2024.8.04.0001')
        ->and($planilha->getCell('K1')->getValue())->toBeNull();

    unlink($arquivo);

    $headings = (new PlanilhaComunicacoes([], comAlvo: true))->headings();
    expect(array_slice($headings, -2))->toBe(['Alvo', 'Lida']);
});

it('gera PDF', function () {
    $e = gerar('pdf');
    $pdf = Storage::disk('local')->get($e->arquivo);

    expect($pdf)->toStartWith('%PDF-')
        ->and(strlen($pdf))->toBeGreaterThan(1000)
        ->and(strlen($pdf))->toBeLessThan(150_000) // fontes com subsetting
        ->and($e->total_itens)->toBe(1);
});

it('o modelo do PDF escapa o conteúdo e mostra os campos', function () {
    $html = view('exports.comunicacoes', [
        'linhas' => [new LinhaExportacao($this->gateway->comunicacoes[0], 'OAB 12345/AM')],
        'comAlvo' => true,
        'origem' => 'Monitoramento',
        'geradaEm' => now(),
    ])->render();

    expect($html)->toContain('0001234-56.2024.8.04.0001')
        ->and($html)->toContain('Helena Prado (OAB 12345/AM)')
        ->and($html)->toContain('OAB 12345/AM')
        ->and($html)->toContain('Fica intimada &amp; ciente.')
        ->and($html)->not->toContain('<script>x</script>')
        ->and($html)->toContain('=HYPERLINK(&quot;http://mal&quot;)');
});

it('apaga o arquivo junto com o registro', function () {
    $e = gerar('csv');
    Storage::disk('local')->assertExists($e->arquivo);

    $e->delete();

    Storage::disk('local')->assertMissing($e->arquivo);
});
