<?php

namespace App\Providers;

use App\Services\Djen\DjenClient;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DjenClient::class, fn ($app): DjenClient => new DjenClient(
            $app->make(HttpFactory::class),
            $app->make(CacheRepository::class),
            $app['config']->get('djen'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Limites de chamadas à Comunica API (CNJ).
     *
     * - "djen": usado pelos Jobs via middleware RateLimited('djen'), global.
     * - "djen-consulta": consulta pública, por IP (middleware throttle:djen-consulta).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('djen', fn (): Limit => Limit::perMinute(
            (int) config('djen.rate_limit.per_minute'),
        ));

        RateLimiter::for('djen-consulta', fn (Request $request): Limit => Limit::perMinute(
            (int) config('djen.rate_limit.public_per_minute'),
        )->by($request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
