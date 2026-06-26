<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentScorecardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'agent_name',
    'agent_email',
    'scorecard_date',
    'status',
    'total_calls',
    'connected_calls',
    'conversions',
    'talk_time_seconds',
    'conversion_rate',
    'top_outcomes',
    'raw_payload',
])]
class AgentScorecard extends Model
{
    /** @use HasFactory<AgentScorecardFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'total_calls' => 'integer',
            'connected_calls' => 'integer',
            'conversions' => 'integer',
            'talk_time_seconds' => 'float',
            'conversion_rate' => 'float',
        ];
    }
}
