<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentKpiTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'daily_call_target',
    'conversion_rate_target',
])]
class AgentKpiTarget extends Model
{
    /** @use HasFactory<AgentKpiTargetFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'daily_call_target' => 'integer',
            'conversion_rate_target' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
