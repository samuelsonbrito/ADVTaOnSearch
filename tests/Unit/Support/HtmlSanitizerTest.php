<?php

use App\Support\HtmlSanitizer;

function sanitizar(string $html): string
{
    return (new HtmlSanitizer)->sanitize($html);
}

it('mantém a formatação permitida', function () {
    expect(sanitizar('<p>Texto <strong>forte</strong> e <em>itálico</em><br>linha</p>'))
        ->toBe('<p>Texto <strong>forte</strong> e <em>itálico</em><br>linha</p>');
});

it('remove scripts, estilos e iframes com o conteúdo', function () {
    expect(sanitizar('<p>ok</p><script>alert(1)</script><style>p{}</style><iframe src="x"></iframe>'))
        ->toBe('<p>ok</p>');
});

it('remove todos os atributos, inclusive eventos e estilos', function () {
    expect(sanitizar('<p onclick="x()" style="color:red" class="a">t</p>'))->toBe('<p>t</p>');
});

it('desembrulha tags não permitidas mantendo o texto', function () {
    expect(sanitizar('<p><a href="javascript:alert(1)">clique</a> <img src=x onerror=alert(1)><font>f</font></p>'))
        ->toBe('<p>clique f</p>');
});

it('preserva acentuação e entidades', function () {
    expect(sanitizar('<p>Intimação &amp; citação — São João</p>'))->toBe('<p>Intimação &amp; citação — São João</p>');
});

it('lida com texto puro e vazio', function () {
    expect(sanitizar('apenas texto'))->toBe('apenas texto')
        ->and(sanitizar('   '))->toBe('');
});

it('remove comentários', function () {
    expect(sanitizar('<p>a<!-- segredo --></p>'))->toBe('<p>a</p>');
});
