<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

it('registra o limitador global "djen" usado pelos Jobs', function () {
    config(['djen.rate_limit.per_minute' => 15]);

    $limit = RateLimiter::limiter('djen')(new stdClass);

    expect($limit->maxAttempts)->toBe(15)
        ->and($limit->decaySeconds)->toBe(60);
});

it('registra o limitador "djen-consulta" por IP', function () {
    config(['djen.rate_limit.public_per_minute' => 30]);

    $request = Request::create('/consulta', server: ['REMOTE_ADDR' => '200.1.2.3']);
    $limit = RateLimiter::limiter('djen-consulta')($request);

    expect($limit->maxAttempts)->toBe(30)
        ->and($limit->key)->toBe('200.1.2.3');
});
