<?php

namespace App\Models;

use App\Domain\Revenue\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'amount_minor',
        'currency',
        'status',
        'paid_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
