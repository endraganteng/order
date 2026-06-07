<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\RackProductRepositoryInterface::class,
            \App\Repositories\RackProductRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\CashierTaskRepositoryInterface::class,
            \App\Repositories\CashierTaskRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\WaiterTaskRepositoryInterface::class,
            \App\Repositories\WaiterTaskRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\AttendanceRepositoryInterface::class,
            \App\Repositories\AttendanceRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\BonusRepositoryInterface::class,
            \App\Repositories\BonusRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\SupplierRepositoryInterface::class,
            \App\Repositories\SupplierRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\WaiterRepositoryInterface::class,
            \App\Repositories\WaiterRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\RackRepositoryInterface::class,
            \App\Repositories\RackRepository::class
        );

        // Fix cURL error 60 (SSL certificate problem) pada local dev
        if (app()->environment('local')) {
            $candidatePaths = array_filter([
                env('SSL_CA_BUNDLE'),
                base_path('cacert.pem'),
                storage_path('app/cacert.pem'),
                ini_get('curl.cainfo') ?: null,
                ini_get('openssl.cafile') ?: null,
            ]);

            foreach ($candidatePaths as $caPath) {
                if (! is_string($caPath) || $caPath === '' || ! is_file($caPath)) {
                    continue;
                }

                $resolvedPath = realpath($caPath) ?: $caPath;
                $normalizedPath = str_replace('\\', '/', $resolvedPath);

                putenv('CURL_CA_BUNDLE='.$normalizedPath);
                putenv('SSL_CERT_FILE='.$normalizedPath);
                ini_set('curl.cainfo', $normalizedPath);
                ini_set('openssl.cafile', $normalizedPath);

                break;
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);

        // DB-backed feature flags override config('features.*') so they can be
        // toggled from the settings UI. Guarded: skip if table not yet migrated.
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('app_settings')) {
                app(\App\Services\FeatureFlagService::class)->applyToConfig();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        RateLimiter::for('waiter-poll', function (Request $request) {
            $waiterId = (string) $request->session()->get('waiter_id', '');
            $waiterIdentity = $waiterId !== '' ? 'waiter:'.$waiterId : 'ip:'.$request->ip();
            $clientIdentity = $waiterIdentity.':session:'.$request->session()->getId();

            return [
                Limit::perMinute(180)->by('waiter-poll:'.$waiterIdentity),
                Limit::perMinute(60)->by('waiter-poll-client:'.$clientIdentity),
            ];
        });

        RateLimiter::for('waiter-sync-due', function (Request $request) {
            $waiterId = (string) $request->session()->get('waiter_id', '');
            $waiterIdentity = $waiterId !== '' ? 'waiter:'.$waiterId : 'ip:'.$request->ip();
            $clientIdentity = $waiterIdentity.':session:'.$request->session()->getId();

            return [
                Limit::perMinute(60)->by('waiter-sync-due:'.$waiterIdentity),
                Limit::perMinute(20)->by('waiter-sync-due-client:'.$clientIdentity),
            ];
        });

        RateLimiter::for('waiter-activity-store', function (Request $request) {
            $waiterId = (string) $request->session()->get('waiter_id', '');
            $identity = $waiterId !== '' ? 'waiter:'.$waiterId : 'ip:'.$request->ip();

            return Limit::perMinute(40)->by($identity);
        });
    }
}
