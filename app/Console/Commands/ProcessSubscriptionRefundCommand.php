<?php

namespace App\Console\Commands;

use App\Domain\Refunds\Actions\CreateSubscriptionRefundAction;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ProcessSubscriptionRefundCommand extends Command
{
    protected $signature = 'refunds:process
                            {subscription : Subscription ID}
                            {--cancel-date= : Cancellation date (YYYY-MM-DD)}';

    protected $description = 'Process a standard unused-days refund for a subscription';

    public function handle(CreateSubscriptionRefundAction $action): int
    {
        $subscription = Subscription::query()->find($this->argument('subscription'));

        if ($subscription === null) {
            $this->error('Subscription not found.');

            return self::FAILURE;
        }

        $cancelDateOption = $this->option('cancel-date');

        if ($cancelDateOption !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $cancelDateOption)) {
            $this->error('Invalid cancel-date format. Use YYYY-MM-DD.');

            return self::FAILURE;
        }

        $cancellationDate = $cancelDateOption !== null
            ? Carbon::parse((string) $cancelDateOption)->startOfDay()
            : now()->startOfDay();

        try {
            $refund = $action->execute($subscription, $cancellationDate);

            $this->info(sprintf(
                'Refund processed: %d minor (%s) for subscription %d.',
                $refund->amount_minor,
                $refund->currency,
                $subscription->id,
            ));

            return self::SUCCESS;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
