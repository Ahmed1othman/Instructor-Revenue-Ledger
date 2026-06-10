<?php

namespace App\Filament\Resources\SubscriptionResource\Actions;

use App\Domain\Money\Money;
use App\Domain\Refunds\Actions\CreateSubscriptionRefundAction;
use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Enums\SubscriptionStatus;
use App\Domain\Revenue\Services\RefundCalculationService;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
            ->form([
                DatePicker::make('cancellation_date')
                    ->label('Cancellation date')
                    ->default(now()->toDateString())
                    ->required()
                    ->maxDate(fn (Subscription $record): string => $record->ends_at->toDateString())
                    ->minDate(fn (Subscription $record): string => $record->starts_at->toDateString()),
            ])
            ->modalHeading('Refund Unused Days')
            ->modalDescription(function (Subscription $record, array $data): string {
                $preview = $this->previewAmount($record, $data['cancellation_date'] ?? now()->toDateString());

                return sprintf(
                    'Standard refund for unused future days only. Cancellation day counts as used; refund starts the next day. Preview refund amount: %s.',
                    $preview,
                );
            })
            ->requiresConfirmation()
            ->visible(fn (Subscription $record): bool => $record->status !== SubscriptionStatus::Refunded)
            ->action(function (Subscription $record, array $data): void {
                $cancellationDate = Carbon::parse($data['cancellation_date'])->startOfDay();
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

    private function previewAmount(Subscription $record, string $cancellationDate): string
    {
        $payment = Payment::query()
            ->where('subscription_id', $record->id)
            ->where('status', PaymentStatus::Succeeded)
            ->first();

        if ($payment === null) {
            return Money::formatMinor(0, $record->currency);
        }

        $amountMinor = app(RefundCalculationService::class)->preview(
            $payment,
            $record,
            Carbon::parse($cancellationDate)->startOfDay(),
        );

        return Money::formatMinor($amountMinor, $record->currency);
    }
}
