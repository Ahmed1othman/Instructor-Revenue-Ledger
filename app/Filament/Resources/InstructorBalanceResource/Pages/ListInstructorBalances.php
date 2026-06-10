<?php

namespace App\Filament\Resources\InstructorBalanceResource\Pages;

use App\Filament\Resources\InstructorBalanceResource;
use Filament\Resources\Pages\ListRecords;

class ListInstructorBalances extends ListRecords
{
    protected static string $resource = InstructorBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
