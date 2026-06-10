<?php

namespace App\Models;

use App\Domain\Payouts\Enums\PayoutBatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'initiated_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutBatchStatus::class,
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }
}
