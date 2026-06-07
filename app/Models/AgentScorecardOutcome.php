<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentScorecardOutcomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agent_scorecard_id',
    'outcome',
    'count',
])]
class AgentScorecardOutcome extends Model
{
    /** @use HasFactory<AgentScorecardOutcomeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'count' => 'integer',
        ];
    }

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(AgentScorecard::class, 'agent_scorecard_id');
    }
}
