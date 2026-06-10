<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonConsumption extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'student_id',
        'course_id',
        'instructor_id',
        'valid_watched_seconds',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_watched_seconds' => 'integer',
            'consumed_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }
}
