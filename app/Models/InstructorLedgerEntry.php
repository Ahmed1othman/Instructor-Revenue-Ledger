<?php

namespace App\Models;

use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'subscription_id',
        'settlement_period_id',
        'payout_id',
        'type',
        'direction',
        'amount_minor',
        'currency',
        'idempotency_key',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'direction' => LedgerDirection::class,
            'amount_minor' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function settlementPeriod(): BelongsTo
    {
        return $this->belongsTo(SettlementPeriod::class);
    }
}
