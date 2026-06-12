<?php

namespace App\Filament\Widgets;

class TablesSectionHeading extends DashboardSectionHeading
{
    protected static ?int $sort = 50;

    protected function sectionTitle(): string
    {
        return 'Tables';
    }
}
