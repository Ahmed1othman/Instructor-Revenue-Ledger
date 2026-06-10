<?php

namespace App\Filament\Resources\InstructorBalanceResource\RelationManagers;

use App\Domain\Money\Money;
use App\Domain\Payouts\Enums\PayoutStatus;
use App\Models\Payout;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Payout History';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Payout ID'),
                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(
                        fn (int $state, Payout $record): string => Money::formatMinor($state, $record->currency)
                    ),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PayoutStatus $state): string => $state->value),
                Tables\Columns\TextColumn::make('provider_reference')
                    ->label('Provider Reference')
                    ->getStateUsing(
                        fn (Payout $record): ?string => $record->attempts()->latest('attempted_at')->value('provider_reference')
                    )
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed / Confirmed')
                    ->getStateUsing(function (Payout $record): ?string {
                        $attempt = $record->attempts()->latest('attempted_at')->first();
                        $timestamp = $attempt?->attempted_at ?? $record->updated_at;

                        return $timestamp?->toDateTimeString();
                    }),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(Model $record): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }
}
