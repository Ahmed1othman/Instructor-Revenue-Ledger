<?php

namespace App\Filament\Resources\InstructorBalanceResource\Pages;

use App\Filament\Resources\InstructorBalanceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInstructorBalance extends ViewRecord
{
    protected static string $resource = InstructorBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
