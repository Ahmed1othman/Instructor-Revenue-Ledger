<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Models\InstructorBalance;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopInstructorsByOutstanding extends BaseWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top instructors by outstanding';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InstructorBalance::query()
                    ->with('instructor')
                    ->where('outstanding_minor', '>', 0)
                    ->orderByDesc('outstanding_minor')
                    ->limit(5),
            )
            ->columns([
                Tables\Columns\TextColumn::make('instructor.name')
                    ->label('Instructor'),
                Tables\Columns\TextColumn::make('outstanding_minor')
                    ->label('Outstanding')
                    ->formatStateUsing(
                        fn (int $state, InstructorBalance $record): string => Money::formatMinor($state, $record->currency),
                    ),
                Tables\Columns\TextColumn::make('currency'),
            ])
            ->paginated(false);
    }
}
