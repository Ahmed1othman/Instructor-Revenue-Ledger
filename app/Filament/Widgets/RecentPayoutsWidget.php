<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Models\Payout;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentPayoutsWidget extends BaseWidget
{
    protected static ?int $sort = 55;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent payouts';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payout::query()
                    ->with('instructor')
                    ->orderByDesc('created_at')
                    ->limit(5),
            )
            ->columns([
                Tables\Columns\TextColumn::make('instructor.name')
                    ->label('Instructor'),
                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(
                        fn (int $state, Payout $record): string => Money::formatMinor($state, $record->currency),
                    ),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
            ])
            ->paginated(false);
    }
}
