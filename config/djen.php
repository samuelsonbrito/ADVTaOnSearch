<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Comunica API (DJEN / CNJ)
    |--------------------------------------------------------------------------
    |
    | Toda a comunicação com a API passa por App\Services\Djen\DjenClient.
    | A consulta pública (GET /comunicacao) não exige autenticação, mas a
    | API bloqueia requisições de IPs fora do Brasil.
    |
    */

    'base_url' => rtrim((string) env('DJEN_BASE_URL', 'https://comunicaapi.pje.jus.br/api/v1'), '/'),

    'user_agent' => env('DJEN_USER_AGENT', 'AdvTaOnSearch/1.0'),

    // Segundos.
    'timeout' => (int) env('DJEN_TIMEOUT', 20),
    'connect_timeout' => (int) env('DJEN_CONNECT_TIMEOUT', 5),

    // Tentativas extras em erros de conexão, 429 e 5xx, com backoff exponencial.
    'retry' => [
        'times' => (int) env('DJEN_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('DJEN_RETRY_SLEEP_MS', 500),
    ],

    'itens_por_pagina' => (int) env('DJEN_ITENS_POR_PAGINA', 20),

    // Segundos que uma consulta fica em cache (chave baseada nos filtros).
    'cache_ttl' => (int) env('DJEN_CACHE_TTL', 600),

    'rate_limit' => [
        // Chamadas ao CNJ por minuto feitas pelos Jobs (middleware RateLimited).
        'per_minute' => (int) env('DJEN_RATE_LIMIT_PER_MINUTE', 15),
        // Consultas públicas por minuto, por IP.
        'public_per_minute' => (int) env('DJEN_PUBLIC_RATE_LIMIT_PER_MINUTE', 30),
    ],

    'monitoring' => [
        // Horário diário (HH:MM) em que o Job de monitoramento roda.
        'time' => env('DJEN_MONITORING_TIME', '07:00'),
        'timezone' => env('DJEN_MONITORING_TIMEZONE', 'America/Manaus'),
        // Quantos dias para trás cada execução consulta.
        'lookback_days' => (int) env('DJEN_MONITORING_LOOKBACK_DAYS', 3),
    ],

    'whatsapp' => [
        // Drivers disponíveis: log. (Evolution API, Z-API e Meta Cloud API: a implementar.)
        'driver' => env('DJEN_WHATSAPP_DRIVER', 'log'),
    ],

];
