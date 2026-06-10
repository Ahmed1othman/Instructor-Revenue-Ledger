<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Models\InstructorBalance;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopInstructorsByEarned extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top instructors by earned';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InstructorBalance::query()
                    ->with('instructor')
                    ->orderByDesc('total_earned_minor')
                    ->limit(5),
            )
            ->columns([
                Tables\Columns\TextColumn::make('instructor.name')
                    ->label('Instructor'),
                Tables\Columns\TextColumn::make('total_earned_minor')
                    ->label('Total earned')
                    ->formatStateUsing(
                        fn (int $state, InstructorBalance $record): string => Money::formatMinor($state, $record->currency),
                    ),
                Tables\Columns\TextColumn::make('currency'),
            ])
            ->paginated(false);
    }
}
