<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resolvedAt = $this->resolved_at;
        $createdAt = $this->created_at;
        $updatedAt = $this->updated_at;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'state' => $this->state,
            'priority' => $this->priority,
            'resolved_at' => $resolvedAt instanceof \DateTimeInterface ? $resolvedAt->format(DATE_ATOM) : null,
            'created_at' => $createdAt instanceof \DateTimeInterface ? $createdAt->format(DATE_ATOM) : null,
            'updated_at' => $updatedAt instanceof \DateTimeInterface ? $updatedAt->format(DATE_ATOM) : null,
            'reporter' => $this->whenLoaded('reporter', function (): ?array {
                /** @var User|null $reporter */
                $reporter = $this->reporter;

                if ($reporter === null) {
                    return null;
                }

                return [
                    'id' => $reporter->id,
                    'name' => $reporter->name,
                    'email' => $reporter->email,
                ];
            }),
            'assignee' => $this->whenLoaded('assignee', function (): ?array {
                /** @var User|null $assignee */
                $assignee = $this->assignee;

                if ($assignee === null) {
                    return null;
                }

                return [
                    'id' => $assignee->id,
                    'name' => $assignee->name,
                    'email' => $assignee->email,
                ];
            }),
            'location' => $this->whenLoaded('location', function (): ?array {
                /** @var Location|null $location */
                $location = $this->location;

                if ($location === null) {
                    return null;
                }

                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'building' => $location->building,
                    'floor' => $location->floor,
                    'room_code' => $location->room_code,
                ];
            }),
            'category' => $this->whenLoaded('category', function (): ?array {
                /** @var Category|null $category */
                $category = $this->category;

                if ($category === null) {
                    return null;
                }

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'icon' => $category->icon,
                ];
            }),
            'state_history' => $this->whenLoaded('stateHistory', fn () => $this->stateHistory->map(function (StateHistory $entry): array {
                return [
                    'id' => $entry->id,
                    'from_state' => $entry->from_state,
                    'to_state' => $entry->to_state,
                    'changed_by' => $entry->changed_by,
                    'comment' => $entry->comment,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ];
            })->values()->all()),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(function (TicketMedia $media): array {
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
