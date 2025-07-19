<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserQuestionResource extends JsonResource
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
            'question' => $this->question,
            'parsed_filters' => $this->parsed_filters,
            'ai_response' => $this->ai_response,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'has_response' => $this->hasResponse(),
            'has_filters' => $this->hasFilters(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}