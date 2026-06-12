<?php

namespace App\Filament\Widgets\Concerns;

use App\Domain\Money\Money;
use App\Domain\Revenue\Services\PlatformFinancialMetricsService;

trait FormatsDashboardMoney
{
    /**
     * @return array{
     *     currency: string,
     *     mixed: bool,
     *     label: string,
     *     description: string|null
     * }
     */
    protected function dashboardCurrencyContext(): array
    {
        return app(PlatformFinancialMetricsService::class)->dashboardCurrencyContext();
    }

    /**
     * @param  array{currency: string, mixed: bool, label: string, description: string|null}  $context
     */
    protected function formatDashboardAmount(int $amountMinor, array $context): string
    {
        if ($context['mixed']) {
            $sign = $amountMinor < 0 ? '-' : '';
            $absolute = abs($amountMinor);

            return sprintf(
                '%s%d.%02d',
                $sign,
                intdiv($absolute, 100),
                $absolute % 100,
            );
        }

        return Money::formatMinor($amountMinor, $context['currency']);
    }

    protected function dashboardMoneyDescription(array $context): ?string
    {
        return $context['description'];
    }
}
