<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Domain\Revenue\Enums\RefundStatus;
use App\Models\Refund;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentRefundsWidget extends BaseWidget
{
    protected static ?int $sort = 54;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent refunds';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Refund::query()
                    ->with(['subscription.user', 'student'])
                    ->where('status', RefundStatus::Completed)
                    ->orderByDesc('processed_at')
                    ->limit(5),
            )
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Student'),
                Tables\Columns\TextColumn::make('cancellation_date')
                    ->label('Cancelled')
                    ->date(),
                Tables\Columns\TextColumn::make('unused_days')
                    ->label('Unused days'),
                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(
                        fn (int $state, Refund $record): string => Money::formatMinor($state, $record->currency),
                    ),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime(),
            ])
            ->paginated(false);
    }
}
