<?php

namespace App\Filament\Widgets;

class RevenueSectionHeading extends DashboardSectionHeading
{
    protected static ?int $sort = 10;

    protected function sectionTitle(): string
    {
        return 'Revenue';
    }
}
