<?php

namespace App\Models;

use App\Domain\Payouts\Enums\PayoutAttemptStatus;
use App\Domain\Payouts\Enums\ProviderResultStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'payout_id',
        'type',
        'status',
        'provider_result',
        'provider_reference',
        'idempotency_key',
        'attempted_at',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutAttemptStatus::class,
            'provider_result' => ProviderResultStatus::class,
            'attempted_at' => 'datetime',
            'response_payload' => 'array',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
