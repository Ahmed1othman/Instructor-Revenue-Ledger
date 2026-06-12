<?php

namespace App\Filament\Widgets;

class RevenueSplitSectionHeading extends DashboardSectionHeading
{
    protected static ?int $sort = 20;

    protected function sectionTitle(): string
    {
        return 'Revenue Split';
    }
}
