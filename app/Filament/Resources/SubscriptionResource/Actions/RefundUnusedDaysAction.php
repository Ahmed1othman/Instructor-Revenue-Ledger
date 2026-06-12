<?php

namespace App\Filament\Resources\SubscriptionResource\Actions;

use App\Domain\Money\Money;
use App\Domain\Refunds\Actions\CreateSubscriptionRefundAction;
use App\Domain\Revenue\Services\SubscriptionRefundEligibilityService;
use App\Models\Refund;
use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use InvalidArgumentException;

class RefundUnusedDaysAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'refundUnusedDays';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Refund Unused Days')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->form(function (Subscription $record): array {
                $preview = app(SubscriptionRefundEligibilityService::class)->standardRefundPreview($record);

                return [
                    Placeholder::make('cancellation_date')
                        ->label('Cancellation date')
                        ->content($preview['cancellation_date']),
                    Placeholder::make('refund_starts_on')
                        ->label('Refund starts on')
                        ->content($preview['refund_starts_on']),
                    Placeholder::make('used_days')
                        ->label('Used days')
                        ->content((string) $preview['used_days']),
                    Placeholder::make('unused_days')
                        ->label('Unused days')
                        ->content((string) $preview['unused_days']),
                    Placeholder::make('refund_amount')
                        ->label('Refund amount')
                        ->content(Money::formatMinor($preview['amount_minor'], $record->currency)),
                ];
            })
            ->modalHeading('Refund Unused Days')
            ->modalDescription(
                'Standard refund uses today as the cancellation date. The cancellation day counts as used; refund starts the next calendar day.',
            )
            ->requiresConfirmation()
            ->visible(
                fn (Subscription $record): bool => app(SubscriptionRefundEligibilityService::class)
                    ->canOfferStandardRefund($record),
            )
            ->action(function (Subscription $record): void {
                $cancellationDate = app(SubscriptionRefundEligibilityService::class)->standardCancellationDate();
                $idempotencyKey = sprintf(
                    'refund:%d:%s',
                    $record->id,
                    $cancellationDate->toDateString(),
                );

                $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    Notification::make()
                        ->title('Refund already processed')
                        ->body(sprintf(
                            'Existing refund of %s for cancellation date %s.',
                            Money::formatMinor($existing->amount_minor, $existing->currency),
                            $existing->cancellation_date->toDateString(),
                        ))
                        ->warning()
                        ->send();

                    return;
                }

                try {
                    $refund = app(CreateSubscriptionRefundAction::class)->execute($record, $cancellationDate);

                    Notification::make()
                        ->title('Refund processed')
                        ->body(sprintf(
                            'Refunded %s for %d unused day(s).',
                            Money::formatMinor($refund->amount_minor, $refund->currency),
                            $refund->unused_days,
                        ))
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('Refund failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
