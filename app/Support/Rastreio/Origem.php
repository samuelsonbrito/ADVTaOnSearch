<?php

namespace App\Support\Rastreio;

use Illuminate\Http\Request;

/**
 * De onde veio uma requisição, já pseudonimizado (LGPD):
 * - visitante: HMAC de IP + User-Agent com a APP_KEY, 16 caracteres. Permite
 *   contar visitantes únicos sem guardar o IP;
 * - ip_mascarado: IPv4 com o último octeto zerado, IPv6 só com o prefixo /48.
 */
final class Origem
{
    /** @return array<string, string|bool|null> */
    public static function de(Request $request): array
    {
        $ip = (string) $request->ip();
        $ua = (string) $request->userAgent();
        $agente = Agente::de($ua);
        $referer = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        return [
            'visitante' => self::visitante($ip, $ua),
            'ip_mascarado' => self::mascararIp($ip),
            'dispositivo' => $agente->dispositivo,
            'navegador' => $agente->navegador,
            'sistema' => $agente->sistema,
            'bot' => $agente->bot,
            'referer_host' => is_string($referer) && $referer !== $request->getHost() ? mb_substr($referer, 0, 120) : null,
        ];
    }

    public static function visitante(string $ip, string $ua): string
    {
        return substr(hash_hmac('sha256', $ip.'|'.$ua, (string) config('app.key')), 0, 16);
    }

    public static function mascararIp(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = (string) inet_pton($ip);

            return (string) inet_ntop(substr($bin, 0, 6).str_repeat("\0", 10));
        }

        return null;
    }
}
