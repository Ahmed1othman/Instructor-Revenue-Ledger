<?php

namespace App\Filament\Widgets;

class SubscriptionsSectionHeading extends DashboardSectionHeading
{
    protected static ?int $sort = 40;

    protected function sectionTitle(): string
    {
        return 'Subscriptions';
    }
}
