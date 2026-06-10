<?php

namespace App\Models;

use App\Domain\Revenue\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'payment_id',
        'student_id',
        'amount_minor',
        'currency',
        'cancellation_date',
        'refund_starts_on',
        'used_days',
        'unused_days',
        'status',
        'reason',
        'idempotency_key',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'used_days' => 'integer',
            'unused_days' => 'integer',
            'cancellation_date' => 'date',
            'refund_starts_on' => 'date',
            'status' => RefundStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
