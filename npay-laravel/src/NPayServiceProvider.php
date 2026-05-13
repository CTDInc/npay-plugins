<?php

namespace NPay\Laravel;

use Illuminate\Support\ServiceProvider;
use NPay\Laravel\Console\Commands\NPayInstallCommand;
use NPay\Laravel\Http\Middleware\VerifyNPayWebhook;

class NPayServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký các binding của package.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/npay.php', 'npay');

        $this->app->singleton('npay', function ($app) {
            return new NPay($app['config']->get('npay', []));
        });

        $this->app->alias('npay', NPay::class);
    }

    /**
     * Khởi động package.
     */
    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/webhook.php');

        // Load migrations để chạy ngay không cần publish (tuỳ chọn)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'npay');

        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/npay.php' => config_path('npay.php'),
            ], 'npay-config');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'npay-migrations');

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/npay'),
            ], 'npay-views');

            // Publish routes
            $this->publishes([
                __DIR__ . '/../routes/webhook.php' => base_path('routes/npay.php'),
            ], 'npay-routes');

            // Commands
            $this->commands([
                NPayInstallCommand::class,
            ]);
        }

        // Register middleware alias
        $router = $this->app['router'];
        $router->aliasMiddleware('npay.webhook', VerifyNPayWebhook::class);
    }

    /**
     * Các service mà provider cung cấp.
     */
    public function provides(): array
    {
        return ['npay', NPay::class];
    }
}
