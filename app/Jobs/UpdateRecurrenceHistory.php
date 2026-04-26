<?php

namespace App\Jobs;

use App\Models\LocationIncidentHistory;
use App\Models\Ticket;
use Carbon\CarbonInterval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateRecurrenceHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Ticket $ticket, public string $correlationId = '') {}

    public function handle(): void
    {
        if (config('ai.recurrence.enabled') !== true) {
            return;
        }

        $ticket = $this->ticket;
        if ($ticket->state !== 'resolved' && $ticket->resolved_at === null) {
            return;
        }

        $resolvedAt = $ticket->resolved_at ?? now();
        $createdAt = $ticket->created_at ?? $resolvedAt;
        $resolutionSeconds = max(0, $resolvedAt->diffInSeconds($createdAt));

        $history = LocationIncidentHistory::firstOrNew([
            'location_id' => $ticket->location_id,
            'category_id' => $ticket->category_id,
        ]);

        $currentCount = (int) ($history->recurrence_count ?? 0);
        $currentAvgSeconds = $this->intervalToSeconds($history->avg_resolution_time);
        $newCount = $currentCount + 1;
        $newAvgSeconds = (int) round((($currentAvgSeconds * $currentCount) + $resolutionSeconds) / $newCount);

        $history->recurrence_count = $newCount;
        $history->last_resolved_at = $resolvedAt;
        $history->avg_resolution_time = $this->formatSeconds($newAvgSeconds);
        $history->save();
    }

    private function intervalToSeconds(?string $interval): int
    {
        if ($interval === null || $interval === '') {
            return 0;
        }

        $parts = explode(':', $interval);
        if (count($parts) === 3) {
            return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + (int) $parts[2];
        }

        if (count($parts) === 2) {
            return ((int) $parts[0] * 60) + (int) $parts[1];
        }

        return (int) $interval;
    }

    private function formatSeconds(int $seconds): string
    {
        return CarbonInterval::seconds(max(0, $seconds))
            ->cascade()
            ->format('%H:%I:%S');
    }
}
