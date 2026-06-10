<?php

namespace App\Providers;

use App\Domain\Payouts\Contracts\PayoutProvider;
use App\Domain\Payouts\Providers\MockPayoutProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PayoutProvider::class, MockPayoutProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
