<?php

use App\Data\Djen\AdvogadoData;
use App\Data\Djen\ComunicacaoData;
use App\Http\Resources\Djen\ComunicacaoResource;
use App\Services\Djen\Exceptions\DjenUnexpectedResponseException;
use App\Services\Djen\Mapping\ComunicacaoApiMapper;

beforeEach(function () {
    $this->json = json_decode(file_get_contents(base_path('tests/Fixtures/djen/comunicacao.json')), true, flags: JSON_THROW_ON_ERROR);
    $this->mapper = new ComunicacaoApiMapper;
    $this->porHash = fn (string $hash): ComunicacaoData => collect($this->mapper->resultado($this->json, 1, 100)->comunicacoes)->firstWhere('hash', $hash);
});

it('lê o envelope com count e items', function () {
    $r = $this->mapper->resultado($this->json, 2, 100);

    expect($r->comunicacoes)->toHaveCount(8)
        ->and($r->total)->toBe(10000)
        ->and($r->pagina)->toBe(2)
        ->and($r->ultimaPagina())->toBe(100);
});

it('mapeia os campos de uma comunicação', function () {
    $c = ($this->porHash)('XDVMlJ3NoYGVIAhVTp57ebZLzE4gOP');

    expect($c->tribunal)->toBe('TJMG')
        ->and($c->orgaoJulgador)->toBe('TJMG - 6ª CÂMARA CÍVEL')
        ->and($c->tipoComunicacao)->toBe('Intimação')
        ->and($c->tipoDocumento)->toBe('Apelação / Reexame Necessário')
        ->and($c->dataDisponibilizacao->toDateString())->toBe('2026-11-06')
        ->and($c->numeroProcesso)->toBe('50005671920228130395')
        ->and($c->numeroProcessoFormatado())->toBe('5000567-19.2022.8.13.0395')
        ->and(array_map(fn ($d) => $d->nome, $c->destinatarios))->toBe(['JUIZ DE DIREITO DA 1ª VARA DE EXEMPLO', 'MUNICIPIO DE EXEMPLO', 'PARTE FICTICIA UM'])
        ->and($c->advogados[0])->toEqual(new AdvogadoData('ADVOGADA FICTICIA UM', '95187', 'MG'))
        ->and($c->linkDocumento)->toStartWith('https://www4.tjmg.jus.br/');
});

it('a máscara do processo bate com numeroprocessocommascara da API em todos os itens', function () {
    foreach ($this->json['items'] as $item) {
        expect($this->mapper->comunicacao($item)->numeroProcessoFormatado())->toBe($item['numeroprocessocommascara']);
    }
});

it('remove destinatários e advogados repetidos', function () {
    $c = ($this->porHash)('kW5ljVaJnRkDi1hDTelJAk6Ave9mDO');

    expect(array_map(fn ($d) => $d->nome, $c->destinatarios))->toBe(['B. F. D. R.', 'E. L.'])
        ->and($c->advogados)->toHaveCount(1)
        ->and($c->advogados[0]->numeroOab)->toBe('309-B');
});

it('preserva OAB alfanumérica', function () {
    $c = ($this->porHash)('vEkj97AdKYWMf5hQTOexA16eLPQ1Gg');

    expect(array_map(fn ($a) => $a->numeroOab, $c->advogados))->toBe(['11811N', '20413-O']);
});

it('aceita link nulo e lista de advogados vazia', function () {
    $c = ($this->porHash)('LrEbP1yJdRl5Coh1T7E5av6eXxa8G2');

    expect($c->linkDocumento)->toBeNull()
        ->and($c->advogados)->toBe([])
        ->and($c->tipoComunicacao)->toBe('Lista de distribuição');
});

it('converte texto puro com entidades e quebras de linha em HTML seguro', function () {
    $c = ($this->porHash)('vEkj97AdKYWMf5hQTOexA16eLPQ1Gg');

    expect($c->textoHtml)->toContain('PODER JUDICIÁRIO<br>')
        ->and($c->textoHtml)->not->toContain('&nbsp;')
        ->and($c->textoHtml)->not->toContain('&Aacute;')
        ->and($c->textoPuro())->toStartWith('PODER JUDICIÁRIO DO ESTADO DE GOIÁS Autos n°: 6125839-20.2026.8.09.0090');
});

it('escapa texto puro que tenha sinais de menor/maior', function () {
    $c = $this->mapper->comunicacao(array_merge($this->json['items'][6], ['texto' => "Valor x < y e a > b\nfim"]));

    expect($c->textoHtml)->toBe("Valor x &lt; y e a &gt; b<br>\nfim");
});

it('gera trecho limpo a partir de documentos HTML completos (style, doctype, xml)', function (string $hash, string $inicio) {
    expect(($this->porHash)($hash)->trecho(60))->toStartWith($inicio)
        ->not->toContain('font-family')
        ->not->toContain('<');
})->with([
    'html + head/style' => ['vKAPnkeQmZdduPhBhj8eOmZd5o94bD', 'PROCEDIMENTO COMUM CÍVEL Nº 5010611-54.2025.8.21.0077/RS'],
    'doctype + style com CSS' => ['MlkWByzDGYz9hkhvTQmBk8ZebmAjON', 'PROCEDIMENTO DO JUIZADO ESPECIAL CÍVEL Nº 0318094'],
    'declaração xml' => ['Jqo8LEjMvRgKt8h5TGKOW7Rmrz2A1x', 'TURMA NACIONAL DE UNIFORMIZAÇÃO Pauta de Julgamentos'],
    'tabela com style' => ['XvLko4Pw16XzcwhoTGjd3MYbAKq5Vd', 'Processo: 0025850-66.2026.8.16.0182(Embargos de Declaração'],
]);

it('o inteiro teor sanitizado não traz CSS, atributos nem a declaração xml', function () {
    foreach ($this->json['items'] as $item) {
        $html = (new ComunicacaoResource($this->mapper->comunicacao($item)))->resolve()['textoHtml'];

        expect($html)->not->toContain('font-family')
            ->not->toContain('style=')
            ->not->toContain('<?xml')
            ->not->toContain('<html')
            ->not->toContain('data-');
    }
});

it('rejeita resposta sem items', function () {
    expect(fn () => $this->mapper->resultado(['status' => 'success'], 1, 20))->toThrow(DjenUnexpectedResponseException::class);
});

it('ignora itens sem hash e usa o tamanho da lista se count faltar', function () {
    $json = ['items' => [$this->json['items'][0], ['id' => 1], 'lixo']];

    $r = $this->mapper->resultado($json, 1, 20);

    expect($r->comunicacoes)->toHaveCount(1)->and($r->total)->toBe(1);
});
