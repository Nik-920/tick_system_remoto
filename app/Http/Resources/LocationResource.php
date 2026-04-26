<?php

namespace App\Http\Resources;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Location */
class LocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = (string) ($this->qr_generation_status ?? 'pending');
        $qrGeneratedAt = $this->qr_generated_at;
        $createdAt = $this->created_at;
        $updatedAt = $this->updated_at;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'building' => $this->building,
            'floor' => $this->floor,
            'room_code' => $this->room_code,
            'qr_token' => $this->qr_token,
            'qr_image_url' => $this->qr_image_url,
            'qr_generation_status' => $status,
            'qr_image_status' => $status,
            'qr_last_error' => $this->qr_last_error,
            'qr_job_id' => $this->qr_job_id,
            'qr_generated_at' => $qrGeneratedAt instanceof \DateTimeInterface ? $qrGeneratedAt->format(DATE_ATOM) : null,
            'is_active' => (bool) $this->is_active,
            'tickets_count' => $this->whenCounted('tickets'),
            'incident_history_count' => $this->whenCounted('incidentHistory'),
            'created_at' => $createdAt instanceof \DateTimeInterface ? $createdAt->format(DATE_ATOM) : null,
            'updated_at' => $updatedAt instanceof \DateTimeInterface ? $updatedAt->format(DATE_ATOM) : null,
        ];
    }
}
