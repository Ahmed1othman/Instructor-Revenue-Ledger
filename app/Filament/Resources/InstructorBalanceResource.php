<?php

namespace App\Filament\Resources;

use App\Domain\Money\Money;
use App\Filament\Resources\InstructorBalanceResource\Pages;
use App\Filament\Resources\InstructorBalanceResource\RelationManagers;
use App\Models\InstructorBalance;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InstructorBalanceResource extends Resource
{
    protected static ?string $model = InstructorBalance::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $modelLabel = 'Instructor Balance';

    protected static ?string $pluralModelLabel = 'Instructor Balances';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Balance Summary')
                    ->schema([
                        TextEntry::make('instructor.name')
                            ->label('Instructor'),
                        TextEntry::make('currency'),
                        TextEntry::make('total_earned_minor')
                            ->label('Total Earned')
                            ->formatStateUsing(
                                fn (int $state, InstructorBalance $record): string => Money::formatMinor($state, $record->currency)
                            ),
                        TextEntry::make('total_paid_minor')
                            ->label('Total Paid')
                            ->formatStateUsing(
                                fn (int $state, InstructorBalance $record): string => Money::formatMinor($state, $record->currency)
                            ),
                        TextEntry::make('outstanding_minor')
                            ->label('Outstanding')
                            ->formatStateUsing(
                                fn (int $state, InstructorBalance $record): string => Money::formatMinor($state, $record->currency)
                            ),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('instructor.name')
                    ->label('Instructor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_earned_minor')
                    ->label('Total Earned')
                    ->formatStateUsing(
                        fn (int $state, InstructorBalance $record): string => Money::formatMinor($state, $record->currency)
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_paid_minor')
                    ->label('Total Paid')
                    ->formatStateUsing(
                        fn (int $state, InstructorBalance $record): string => Money::formatMinor($state, $record->currency)
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('outstanding_minor')
                    ->label('Outstanding')
                    ->formatStateUsing(
                        fn (int $state, InstructorBalance $record): string => Money::formatMinor($state, $record->currency)
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PayoutsRelationManager::class,
            RelationManagers\LedgerEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstructorBalances::route('/'),
            'view' => Pages\ViewInstructorBalance::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('instructor');
    }
}
