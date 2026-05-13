<?php

namespace NPay\Laravel\Console\Commands;

use Illuminate\Console\Command;

class NPayInstallCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'npay:install
                            {--force : Ghi đè các file đã được publish trước đó}
                            {--no-migrate : Bỏ qua chạy migrate}';

    /**
     * @var string
     */
    protected $description = 'Cài đặt NPay Laravel: publish config, migrations và chạy migrate.';

    public function handle(): int
    {
        $this->info('🚀 Đang cài đặt NPay Laravel...');

        $force = (bool) $this->option('force');

        $this->info('→ Publish config...');
        $this->call('vendor:publish', [
            '--tag' => 'npay-config',
            '--force' => $force,
        ]);

        $this->info('→ Publish migrations...');
        $this->call('vendor:publish', [
            '--tag' => 'npay-migrations',
            '--force' => $force,
        ]);

        $this->info('→ Publish views...');
        $this->call('vendor:publish', [
            '--tag' => 'npay-views',
            '--force' => $force,
        ]);

        if (!$this->option('no-migrate')) {
            $this->info('→ Chạy migrate...');
            $this->call('migrate');
        }

        $this->newLine();
        $this->info('✅ NPay đã được cài đặt!');
        $this->line('   Tiếp theo, cập nhật các biến môi trường NPAY_* trong file .env:');
        $this->line('   NPAY_API_TOKEN, NPAY_ACCOUNT_NUMBER, NPAY_BANK_BIN, NPAY_WEBHOOK_TOKEN');
        $this->line('   Webhook URL: ' . url(config('npay.webhook_route', 'npay/webhook')));

        return self::SUCCESS;
    }
}
