<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'complaint_number' => $this->complaint_number,
            'complaint_type' => $this->complaint_type,
            'descriptor' => $this->descriptor,
            'agency' => [
                'code' => $this->agency,
                'name' => $this->agency_name,
            ],
            'location' => [
                'borough' => $this->borough,
                'city' => $this->city,
                'address' => $this->incident_address,
                'street_name' => $this->street_name,
                'zip' => $this->incident_zip,
                'coordinates' => $this->when(
                    $this->latitude && $this->longitude,
                    [
                        'lat' => $this->latitude,
                        'lng' => $this->longitude,
                    ]
                ),
            ],
            'status' => $this->status,
            'priority' => $this->priority,
            'dates' => [
                'submitted_at' => $this->submitted_at?->toISOString(),
                'resolved_at' => $this->resolved_at?->toISOString(),
                'due_date' => $this->due_date?->toISOString(),
            ],
            'analysis' => new ComplaintAnalysisResource($this->whenLoaded('analysis')),
            'actions_count' => $this->when(
                $this->relationLoaded('actions'),
                fn() => $this->actions->count()
            ),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}