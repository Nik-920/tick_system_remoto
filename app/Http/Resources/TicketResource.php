<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Ticket */
class TicketResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'state' => $this->state,
            'priority' => $this->priority,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'reporter' => $this->whenLoaded('reporter', function (): ?array {
                if ($this->reporter === null) {
                    return null;
                }

                return [
                    'id' => $this->reporter->id,
                    'name' => $this->reporter->name,
                    'email' => $this->reporter->email,
                ];
            }),
            'assignee' => $this->whenLoaded('assignee', function (): ?array {
                if ($this->assignee === null) {
                    return null;
                }

                return [
                    'id' => $this->assignee->id,
                    'name' => $this->assignee->name,
                    'email' => $this->assignee->email,
                ];
            }),
            'location' => $this->whenLoaded('location', function (): ?array {
                if ($this->location === null) {
                    return null;
                }

                return [
                    'id' => $this->location->id,
                    'name' => $this->location->name,
                    'building' => $this->location->building,
                    'floor' => $this->location->floor,
                    'room_code' => $this->location->room_code,
                ];
            }),
            'category' => $this->whenLoaded('category', function (): ?array {
                if ($this->category === null) {
                    return null;
                }

                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'icon' => $this->category->icon,
                ];
            }),
            'state_history' => $this->whenLoaded('stateHistory', fn () => $this->stateHistory->map(function ($entry): array {
                return [
                    'id' => $entry->id,
                    'from_state' => $entry->from_state,
                    'to_state' => $entry->to_state,
                    'changed_by' => $entry->changed_by,
                    'comment' => $entry->comment,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ];
            })->values()->all()),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(function ($media): array {
                return [
                    'id' => $media->id,
                    'file_url' => $media->file_url,
                    'file_type' => $media->file_type,
                    'uploaded_by' => $media->uploaded_by,
                    'created_at' => $media->created_at?->toIso8601String(),
                ];
            })->values()->all()),
        ];
    }
}
