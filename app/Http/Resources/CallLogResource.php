<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CallLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CallLog
 */
class CallLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'call_log_id' => $this->id,
            'lead_id' => $this->lead_id,
            'user_id' => $this->user_id,
            'duration' => $this->duration,
            'duration_hms' => $this->duration_hms,
            'notes' => $this->notes,
            'outcome' => $this->outcome,
            'callback_at' => $this->callbackReminder?->remind_at
                ? $this->callbackReminder->remind_at->format('Y-m-d H:i:s')
                : null,
            'called_at' => $this->called_at ? $this->called_at->format('Y-m-d H:i:s') : null,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
