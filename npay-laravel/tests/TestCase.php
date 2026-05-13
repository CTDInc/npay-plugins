<?php

namespace NPay\Laravel\Tests;

use NPay\Laravel\NPayServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            NPayServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'NPay' => \NPay\Laravel\Facades\NPay::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('npay.api_token', 'test-api-token');
        $app['config']->set('npay.webhook_token', 'test-webhook-token');
        $app['config']->set('npay.account_number', '0123456789');
        $app['config']->set('npay.bank_bin', '970422');
        $app['config']->set('npay.account_holder', 'NGUYEN VAN A');
    }
}
