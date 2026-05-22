<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
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
        /** @var TicketEmbedding|null $embedding */
        $embedding = $this->relationLoaded('embedding') ? $this->embedding : null;
        $duplicateData = $this->duplicateWarningData($embedding);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'state' => $this->state,
            'priority' => $this->priority,
            'resolved_at' => $this->formatDate($this->resolved_at),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
            'duplicate_warning' => $duplicateData['duplicate_warning'],
            'similar_ticket' => $duplicateData['similar_ticket'],
            'reporter' => $this->whenLoaded('reporter', fn () => $this->mapUser($this->reporter)),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->mapUser($this->assignee)),
            'location' => $this->whenLoaded('location', fn () => $this->mapLocation($this->location)),
            'category' => $this->whenLoaded('category', fn () => $this->mapCategory($this->category)),
            'state_history' => $this->whenLoaded('stateHistory', fn () => $this->mapStateHistory($this->stateHistory)),
            'media' => $this->whenLoaded('media', fn () => $this->mapMedia($this->media)),
        ];
    }

    private function formatDate(?\DateTimeInterface $value): ?string
    {
        return $value ? $value->format(DATE_ATOM) : null;
    }

    /**
     * @return array{duplicate_warning: bool, similar_ticket: array<string, mixed>|null}
     */
    private function duplicateWarningData(?TicketEmbedding $embedding): array
    {
        $duplicateWarning = false;
        $similarTicket = null;

        if ($embedding && $embedding->is_duplicate) {
            /** @var Ticket|null $matchedTicket */
            $matchedTicket = $embedding->relationLoaded('matchedTicket') ? $embedding->matchedTicket : null;

            if ($matchedTicket && in_array($matchedTicket->state, ['open', 'in_progress'], true)) {
                $duplicateWarning = true;
                $similarTicket = [
                    'id' => $matchedTicket->id,
                    'title' => $matchedTicket->title,
                    'state' => $matchedTicket->state,
                    'created_at' => $matchedTicket->created_at?->format(DATE_ATOM),
                    'similarity_score' => $embedding->similarity_score,
                ];
            }
        }

        return [
            'duplicate_warning' => $duplicateWarning,
            'similar_ticket' => $similarTicket,
        ];
    }

    private function mapUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function mapLocation(?Location $location): ?array
    {
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
    }

    private function mapCategory(?Category $category): ?array
    {
        if ($category === null) {
            return null;
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'icon' => $category->icon,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, StateHistory> $entries
     * @return array<int, array<string, mixed>>
     */
    private function mapStateHistory($entries): array
    {
        return $entries->map(function (StateHistory $entry): array {
            return [
                'id' => $entry->id,
                'from_state' => $entry->from_state,
                'to_state' => $entry->to_state,
                'changed_by' => $entry->changed_by,
                'comment' => $entry->comment,
                'created_at' => $entry->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @param \Illuminate\Support\Collection<int, TicketMedia> $entries
     * @return array<int, array<string, mixed>>
     */
    private function mapMedia($entries): array
    {
        return $entries->map(function (TicketMedia $media): array {
            return [
                'id' => $media->id,
                'file_url' => $media->file_url,
                'file_type' => $media->file_type,
                'uploaded_by' => $media->uploaded_by,
                'created_at' => $media->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
