<?php

namespace App\Filament\Widgets;

class PayoutsSectionHeading extends DashboardSectionHeading
{
    protected static ?int $sort = 30;

    protected function sectionTitle(): string
    {
        return 'Payouts';
    }
}
