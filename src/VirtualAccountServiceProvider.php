<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PayZephyr\VirtualAccounts\Console\ReconcileVirtualAccountsCommand;
use PayZephyr\VirtualAccounts\Http\Controllers\WebhookController;
use PayZephyr\VirtualAccounts\Services\DriverFactory;
use PayZephyr\VirtualAccounts\Services\VirtualAccountManager;
use Throwable;

final class VirtualAccountServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/virtual-accounts.php',
            'virtual-accounts'
        );

        $this->app->singleton('virtual-accounts.config', fn () => config('virtual-accounts'));

        $this->app->singleton(DriverFactory::class);

        $this->app->singleton(VirtualAccountManager::class, function ($app) {
            return new VirtualAccountManager(
                $app->make(DriverFactory::class)
            );
        });

        $this->app->alias(
            VirtualAccountManager::class,
            'virtual-accounts'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/virtual-accounts.php' => config_path('virtual-accounts.php'),
            ], 'virtual-accounts-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'virtual-accounts-migrations');

            $this->commands([
                ReconcileVirtualAccountsCommand::class,
            ]);
        }

        $this->registerRoutes();
        $this->scheduleReconciliation();
    }

    protected function registerRoutes(): void
    {
        if (! $this->app->routesAreCached()) {
            $config = app('virtual-accounts.config') ?? config('virtual-accounts', []);
            $webhookPath = $config['webhook']['path'] ?? '/virtual-accounts/webhook';
            $rateLimit = $config['webhook']['rate_limit'] ?? '120,1';

            Route::group([
                'prefix' => $webhookPath,
                'middleware' => ['api', 'throttle:'.$rateLimit],
            ], function () {
                Route::post('/{provider}', [WebhookController::class, 'handle'])
                    ->name('virtual-accounts.webhook');
            });

            // Health check endpoint
            Route::get('/virtual-accounts/health', function (VirtualAccountManager $manager) {
                $providers = [];
                $healthConfig = app('virtual-accounts.config') ?? config('virtual-accounts', []);

                $enabledProviders = array_filter(
                    $healthConfig['providers'] ?? [],
                    fn ($config) => $config['enabled'] ?? false
                );

                foreach ($enabledProviders as $name => $providerConfig) {
                    try {
                        $driver = $manager->driver($name);
                        $providers[$name] = [
                            'healthy' => $driver->getCachedHealthCheck(),
                            'currencies' => $driver->getSupportedCurrencies(),
                        ];
                    } catch (Throwable $e) {
                        $providers[$name] = [
                            'healthy' => false,
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                return response()->json([
                    'status' => 'operational',
                    'providers' => $providers,
                ]);
            })->middleware(['api'])->name('virtual-accounts.health');
        }
    }

    protected function scheduleReconciliation(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if (! config('virtual-accounts.reconciliation.enabled', true)) {
            return;
        }

        $this->app->booted(function () {
            $schedule = config('virtual-accounts.reconciliation.schedule', 'daily');
            $scheduler = $this->app->make(Schedule::class);

            $event = $scheduler->command('virtual-accounts:reconcile');

            match ($schedule) {
                'hourly' => $event->hourly(),
                'weekly' => $event->weekly(),
                default => $event->daily(),
            };
        });
    }
}
