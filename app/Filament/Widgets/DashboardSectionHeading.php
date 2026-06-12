<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

abstract class DashboardSectionHeading extends Widget
{
    protected static string $view = 'filament.widgets.section-heading';

    protected int|string|array $columnSpan = 'full';

    abstract protected function sectionTitle(): string;

    /**
     * @return array<string, string>
     */
    protected function getViewData(): array
    {
        return [
            'title' => $this->sectionTitle(),
        ];
    }
}
