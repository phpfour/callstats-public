<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'phone_number',
    'email',
    'study_destination',
    'source',
    'ielts_score',
    'assigned_to_id',
    'assigned_at',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Lead $lead): void {
            if ($lead->isDirty('assigned_to_id') && $lead->assigned_to_id && ! $lead->isDirty('assigned_at')) {
                $lead->assigned_at = now();
            }
        });
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function lastCall(): HasOne
    {
        return $this->hasOne(CallLog::class)->latestOfMany('called_at');
    }

    public function nextReminder(): HasOne
    {
        return $this->hasOne(Reminder::class)->oldestOfMany('remind_at');
    }
}
