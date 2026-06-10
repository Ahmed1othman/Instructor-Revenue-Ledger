<?php

namespace App\Models;

use App\Domain\Revenue\Enums\SettlementPeriodStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'period_start',
        'period_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => SettlementPeriodStatus::class,
        ];
    }

    public function revenueAllocations(): HasMany
    {
        return $this->hasMany(RevenueAllocation::class);
    }
}
