<?php

namespace App\Models;

use App\Domain\Payouts\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'payout_batch_id',
        'instructor_id',
        'amount_minor',
        'currency',
        'status',
        'balance_snapshot_hash',
        'active_snapshot_key',
        'provider_idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => PayoutStatus::class,
        ];
    }

    public function payoutBatch(): BelongsTo
    {
        return $this->belongsTo(PayoutBatch::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PayoutAttempt::class);
    }
}
