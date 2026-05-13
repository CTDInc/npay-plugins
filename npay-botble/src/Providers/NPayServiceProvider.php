<?php

namespace Botble\NPay\Providers;

use Botble\Base\Supports\ServiceProvider;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Models\Payment;
use Illuminate\Support\Facades\Route;

class NPayServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->setNamespace('plugins/npay')
            ->loadHelpers();

        $this->mergeConfigFrom(__DIR__ . '/../../config/npay.php', 'plugins.npay');
    }

    public function boot(): void
    {
        $this->setNamespace('plugins/npay')
            ->loadAndPublishConfigurations(['npay'])
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->loadMigrations()
            ->loadRoutes(['web', 'api']);

        $this->app->register(HookServiceProvider::class);

        $this->app->booted(function () {
            $this->registerPaymentMethod();
        });
    }

    protected function registerPaymentMethod(): void
    {
        if (! class_exists('PaymentMethods')) {
            return;
        }

        \PaymentMethods::method('npay', [
            'html' => view('plugins/npay::settings')->render(),
        ]);
    }
}
