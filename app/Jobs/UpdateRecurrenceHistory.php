<?php

namespace App\Jobs;

use App\Models\LocationIncidentHistory;
use App\Models\Ticket;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateRecurrenceHistory implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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

        $rawTimestamps = null;
        if ($ticket->exists) {
            $rawTimestamps = DB::table('tickets')
                ->select(['created_at', 'resolved_at'])
                ->where('id', $ticket->id)
                ->first();
        }

        $resolvedAt = $this->resolveDateTime(
            $rawTimestamps?->resolved_at
                ?? $ticket->resolved_at
                ?? $ticket->getRawOriginal('resolved_at')
        ) ?? now();

        $rawCreatedAt = ($rawTimestamps !== null && trim((string) $rawTimestamps->created_at) !== '')
            ? $rawTimestamps->created_at
            : ($ticket->created_at ?? $ticket->getRawOriginal('created_at'));
        $createdAt = $this->resolveDateTime($rawCreatedAt) ?? $resolvedAt;

        $resolutionSeconds = max(0, $createdAt->diffInSeconds($resolvedAt));

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

    private function resolveDateTime(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_int($value)) {
            return Carbon::createFromTimestamp($value);
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    private function formatSeconds(int $seconds): string
    {
        return CarbonInterval::seconds(max(0, $seconds))
            ->cascade()
            ->format('%H:%I:%S');
    }
}
