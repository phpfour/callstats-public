<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentScorecardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'scorecard_date',
    'status',
    'total_calls',
    'connected_calls',
    'conversions',
    'talk_time_seconds',
    'conversion_rate',
    'review',
    'raw_payload',
])]
class AgentScorecard extends Model
{
    /** @use HasFactory<AgentScorecardFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'scorecard_date' => 'date',
            'total_calls' => 'integer',
            'connected_calls' => 'integer',
            'conversions' => 'integer',
            'talk_time_seconds' => 'integer',
            'conversion_rate' => 'decimal:2',
            'review' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(AgentScorecardOutcome::class);
    }
}
