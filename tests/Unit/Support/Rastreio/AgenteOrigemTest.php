<?php

use App\Support\Rastreio\Agente;
use App\Support\Rastreio\Origem;
use Illuminate\Http\Request;

it('reconhece dispositivo, navegador e sistema pelo User-Agent', function (string $ua, string $dispositivo, ?string $navegador, ?string $sistema, bool $bot) {
    $a = Agente::de($ua);

    expect([$a->dispositivo, $a->navegador, $a->sistema, $a->bot])->toBe([$dispositivo, $navegador, $sistema, $bot]);
})->with([
    'chrome no windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', 'desktop', 'Chrome', 'Windows', false],
    'edge' => ['Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/130.0 Safari/537.36 Edg/130.0', 'desktop', 'Edge', 'Windows', false],
    'safari no iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1', 'celular', 'Safari', 'iOS', false],
    'chrome no android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36', 'celular', 'Chrome', 'Android', false],
    'tablet android' => ['Mozilla/5.0 (Linux; Android 13; SM-X200) AppleWebKit/537.36 Chrome/130.0 Safari/537.36', 'tablet', 'Chrome', 'Android', false],
    'firefox no mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14.5; rv:131.0) Gecko/20100101 Firefox/131.0', 'desktop', 'Firefox', 'macOS', false],
    'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'bot', null, null, true],
    'curl' => ['curl/8.5.0', 'bot', null, null, true],
    'vazio' => ['', 'outro', null, null, false],
]);

it('mascara o IP e pseudonimiza o visitante', function () {
    expect(Origem::mascararIp('177.12.34.56'))->toBe('177.12.34.0')
        ->and(Origem::mascararIp('2804:14c:5b:9f00:1:2:3:4'))->toBe('2804:14c:5b::')
        ->and(Origem::mascararIp('nada'))->toBeNull();

    $v = Origem::visitante('177.12.34.56', 'UA');

    expect($v)->toHaveLength(16)
        ->and($v)->toBe(Origem::visitante('177.12.34.56', 'UA'))
        ->and($v)->not->toBe(Origem::visitante('177.12.34.57', 'UA'))
        ->and($v)->not->toContain('177');
});

it('lê a origem de uma requisição sem guardar o IP inteiro', function () {
    $request = Request::create('https://faroljuris.test/consulta', server: [
        'REMOTE_ADDR' => '200.10.20.30',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36',
        'HTTP_REFERER' => 'https://www.google.com/search?q=intimacao',
    ]);

    expect(Origem::de($request))->toMatchArray([
        'ip_mascarado' => '200.10.20.0',
        'dispositivo' => 'desktop',
        'navegador' => 'Chrome',
        'sistema' => 'Windows',
        'bot' => false,
        'referer_host' => 'www.google.com',
    ]);
});
