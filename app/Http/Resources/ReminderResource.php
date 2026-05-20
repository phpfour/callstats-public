<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reminder
 */
class ReminderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reminder_id' => $this->id,
            'lead' => LeadResource::make($this->lead),
            'call_log' => CallLogResource::make($this->callLog),
            'user_id' => $this->user_id,
            'remind_at' => $this->remind_at ? $this->remind_at->format('Y-m-d H:i:s') : null,
            'notes' => $this->notes,
            'type' => $this->type,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
