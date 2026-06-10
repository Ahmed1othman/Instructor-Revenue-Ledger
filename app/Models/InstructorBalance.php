<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstructorBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'currency',
        'total_earned_minor',
        'total_paid_minor',
        'outstanding_minor',
        'last_ledger_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'total_earned_minor' => 'integer',
            'total_paid_minor' => 'integer',
            'outstanding_minor' => 'integer',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function lastLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(InstructorLedgerEntry::class, 'last_ledger_entry_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'instructor_id', 'instructor_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(InstructorLedgerEntry::class, 'instructor_id', 'instructor_id');
    }
}
