<?php

namespace App\Models;

use App\Domain\Revenue\Enums\SettlementGranularity;
use App\Domain\Revenue\Enums\SettlementPeriodStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'granularity',
        'year',
        'month',
        'period_start',
        'period_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'granularity' => SettlementGranularity::class,
            'year' => 'integer',
            'month' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => SettlementPeriodStatus::class,
        ];
    }

    public function isDaily(): bool
    {
        return $this->granularity === SettlementGranularity::Daily;
    }

    public function isMonthly(): bool
    {
        return $this->granularity === SettlementGranularity::Monthly;
    }

    /**
     * @param  Builder<SettlementPeriod>  $query
     * @return Builder<SettlementPeriod>
     */
    public function scopeDaily(Builder $query): Builder
    {
        return $query->where('granularity', SettlementGranularity::Daily);
    }

    /**
     * @param  Builder<SettlementPeriod>  $query
     * @return Builder<SettlementPeriod>
     */
    public function scopeMonthly(Builder $query): Builder
    {
        return $query->where('granularity', SettlementGranularity::Monthly);
    }

    public function revenueAllocations(): HasMany
    {
        return $this->hasMany(RevenueAllocation::class);
    }
}
