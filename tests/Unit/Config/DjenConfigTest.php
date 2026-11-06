<?php

it('carrega a configuração padrão do DJEN', function () {
    expect(config('djen.base_url'))->toBe('https://comunicaapi.pje.jus.br/api/v1')
        ->and(config('djen.timeout'))->toBeInt()->toBeGreaterThan(0)
        ->and(config('djen.retry.times'))->toBeInt()
        ->and(config('djen.itens_por_pagina'))->toBeInt()->toBeLessThanOrEqual(100)
        ->and(config('djen.cache_ttl'))->toBe(600)
        ->and(config('djen.monitoring.time'))->toMatch('/^\d{2}:\d{2}$/')
        ->and(config('djen.whatsapp.driver'))->toBe('log');
});
