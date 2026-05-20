<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CallLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'lead_id',
    'user_id',
    'called_at',
    'duration',
    'notes',
    'outcome',
])]
class CallLog extends Model
{
    /** @use HasFactory<CallLogFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
            'duration' => 'integer',
            'user_id' => 'integer',
            'lead_id' => 'integer',
        ];
    }

    public function getDurationHmsAttribute(): ?string
    {
        return $this->duration !== null ? gmdate('H:i:s', $this->duration) : null;
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function callbackReminder(): HasOne
    {
        return $this->hasOne(Reminder::class)->where('type', 'callback');
    }
}
