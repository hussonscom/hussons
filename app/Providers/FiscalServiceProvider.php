<?php

namespace App\Providers;

use App\Contracts\FiscalProvider;
use App\Services\Fiscal\InternalReceiptProvider;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class FiscalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FiscalProvider::class, function () {
            return match (config('fiscal.driver')) {
                'internal' => new InternalReceiptProvider,
                default => throw new RuntimeException('The configured fiscal provider is not available.'),
            };
        });
    }
}
