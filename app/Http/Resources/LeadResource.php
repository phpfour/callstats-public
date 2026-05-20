<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 */
class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'lead_id' => $this->id,
            'name' => $this->name,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'study_destination' => $this->study_destination,
            'source' => $this->source,
            'ielts_score' => $this->ielts_score,
            'assigned_at' => $this->assigned_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'last_call' => $this->whenLoaded('lastCall', function () {
                if (! $this->lastCall) {
                    return null;
                }

                return [
                    'called_at' => $this->lastCall->called_at?->format('Y-m-d H:i:s'),
                    'outcome' => $this->lastCall->outcome,
                ];
            }),
        ];
    }
}
