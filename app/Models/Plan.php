<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price_minor',
        'currency',
        'instructor_share_bps',
        'duration_days',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'instructor_share_bps' => 'integer',
            'duration_days' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
