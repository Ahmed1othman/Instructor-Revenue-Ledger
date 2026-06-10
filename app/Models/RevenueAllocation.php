<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_period_id',
        'subscription_id',
        'instructor_id',
        'instructor_pool_minor',
        'engagement_weight',
        'allocated_amount_minor',
        'currency',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'instructor_pool_minor' => 'integer',
            'engagement_weight' => 'integer',
            'allocated_amount_minor' => 'integer',
        ];
    }

    public function settlementPeriod(): BelongsTo
    {
        return $this->belongsTo(SettlementPeriod::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }
}
