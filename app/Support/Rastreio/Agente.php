<?php

namespace App\Support\Rastreio;

/**
 * Leitura simples do User-Agent: tipo de dispositivo, navegador, sistema e
 * se é robô. Sem dependência externa; serve para estatística, não para
 * identificar ninguém.
 */
final readonly class Agente
{
    public function __construct(
        public string $dispositivo,
        public ?string $navegador,
        public ?string $sistema,
        public bool $bot,
    ) {}

    private const ROBOS = '/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python-requests|httpclient|headless|lighthouse|pingdom|uptime/i';

    public static function de(?string $ua): self
    {
        $ua = trim((string) $ua);

        if ($ua === '') {
            return new self('outro', null, null, false);
        }

        $bot = (bool) preg_match(self::ROBOS, $ua);

        $sistema = match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/CrOS/i', $ua) => 'ChromeOS',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => null,
        };

        // A ordem importa: Edge e Opera também dizem "Chrome"; Chrome também diz "Safari".
        $navegador = match (true) {
            (bool) preg_match('/Edg(e|A|iOS)?\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/Firefox|FxiOS/i', $ua) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS|Chromium/i', $ua) => 'Chrome',
            (bool) preg_match('/Safari/i', $ua) => 'Safari',
            default => null,
        };

        $dispositivo = match (true) {
            $bot => 'bot',
            (bool) preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $ua) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android/i', $ua) => 'celular',
            $sistema !== null => 'desktop',
            default => 'outro',
        };

        return new self($dispositivo, $navegador, $sistema, $bot);
    }
}
