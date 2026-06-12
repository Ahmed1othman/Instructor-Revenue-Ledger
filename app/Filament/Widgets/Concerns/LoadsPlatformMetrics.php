<?php

namespace App\Filament\Widgets\Concerns;

use App\Domain\Revenue\Services\PlatformFinancialMetricsService;

trait LoadsPlatformMetrics
{
    /**
     * @return array<string, int>
     */
    protected function platformTotals(): array
    {
        return app(PlatformFinancialMetricsService::class)->totals();
    }
}
