<?php

namespace App\Filament\Resources;

use App\Domain\Money\Money;
use App\Domain\Revenue\DTOs\SubscriptionFinancialSummary;
use App\Domain\Revenue\Services\SubscriptionFinancialSummaryService;
use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $modelLabel = 'Subscription';

    protected static ?string $pluralModelLabel = 'Subscriptions';

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
                Section::make('Subscription')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Student'),
                        TextEntry::make('plan.name')
                            ->label('Plan'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('starts_at')
                            ->label('Start date')
                            ->date(),
                        TextEntry::make('ends_at')
                            ->label('End date')
                            ->date(),
                        TextEntry::make('cancelled_at')
                            ->label('Cancellation date')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('refunded_at')
                            ->label('Refunded at')
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Payment lifecycle')
                    ->schema([
                        ViewEntry::make('payment_breakdown_visual')
                            ->view('filament.infolists.subscription.payment-breakdown')
                            ->state(fn (Subscription $record): SubscriptionFinancialSummary => self::summaryFor($record)),
                    ]),
                Section::make('Revenue split')
                    ->schema([
                        ViewEntry::make('revenue_split_visual')
                            ->view('filament.infolists.subscription.revenue-split')
                            ->state(fn (Subscription $record): SubscriptionFinancialSummary => self::summaryFor($record)),
                    ]),
                Section::make('Financial lifecycle')
                    ->schema([
                        ViewEntry::make('lifecycle_flow_visual')
                            ->view('filament.infolists.subscription.lifecycle-flow')
                            ->state(fn (Subscription $record): SubscriptionFinancialSummary => self::summaryFor($record)),
                    ]),
                Section::make('Financial Summary')
                    ->description('Read-only numeric breakdown')
                    ->schema(self::financialSummaryEntries())
                    ->collapsed(),
            ]);
    }

    /**
     * @return array<int, TextEntry>
     */
    private static function financialSummaryEntries(): array
    {
        $money = fn (Subscription $record, string $field): string => Money::formatMinor(
            self::summaryFor($record)->{$field},
            self::summaryFor($record)->currency,
        );

        return [
            TextEntry::make('financial_paid')
                ->label('Original payment')
                ->state(fn (Subscription $record): string => $money($record, 'paidMinor')),
            TextEntry::make('financial_earned')
                ->label('Earned amount')
                ->state(fn (Subscription $record): string => $money($record, 'earnedMinor')),
            TextEntry::make('financial_unearned')
                ->label('Unearned amount')
                ->state(fn (Subscription $record): string => $money($record, 'unearnedMinor')),
            TextEntry::make('financial_refunded')
                ->label('Refunded amount')
                ->state(fn (Subscription $record): string => $money($record, 'refundedMinor')),
            TextEntry::make('financial_remaining_refundable')
                ->label('Remaining refundable')
                ->state(fn (Subscription $record): string => $money($record, 'remainingRefundableMinor')),
            TextEntry::make('financial_platform_contractual_share')
                ->label('Platform contractual share')
                ->state(fn (Subscription $record): string => $money($record, 'platformContractualShareMinor')),
            TextEntry::make('financial_instructor_pool')
                ->label('Instructor pool (contractual)')
                ->state(fn (Subscription $record): string => $money($record, 'instructorPoolMinor')),
            TextEntry::make('financial_instructor_allocated')
                ->label('Instructor pool allocated')
                ->state(fn (Subscription $record): string => $money($record, 'instructorPoolAllocatedMinor')),
            TextEntry::make('financial_unallocated_instructor_pool')
                ->label('Unallocated instructor pool')
                ->state(fn (Subscription $record): string => $money($record, 'unallocatedInstructorPoolMinor')),
            TextEntry::make('financial_total_platform_retained')
                ->label('Total platform retained')
                ->state(fn (Subscription $record): string => $money($record, 'totalPlatformRetainedMinor')),
            TextEntry::make('financial_instructor_paid')
                ->label('Instructor paid')
                ->state(fn (Subscription $record): string => $money($record, 'instructorPaidMinor')),
            TextEntry::make('financial_instructor_outstanding')
                ->label('Instructor outstanding')
                ->state(fn (Subscription $record): string => $money($record, 'instructorOutstandingMinor')),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Start')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('End')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Payment')
                    ->state(function (Subscription $record): string {
                        $summary = self::summaryFor($record);

                        return Money::formatMinor($summary->paidMinor, $summary->currency);
                    }),
                Tables\Columns\TextColumn::make('currency'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'plan']);
    }

    private static function summaryFor(Subscription $record): SubscriptionFinancialSummary
    {
        return app(SubscriptionFinancialSummaryService::class)->forSubscription($record);
    }
}
