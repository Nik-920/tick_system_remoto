<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\CommunityNotificationPreference;
use App\Models\Notification;
use App\Models\User;
use App\Queries\Community\CommunityDigestQuery;
use App\Services\Notifications\NotificationPayload;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommunityDigestService
{
    public function __construct(
        private readonly CommunityDigestQuery $query,
        private readonly NotificationService $notifications,
        private readonly CommunityNotificationPreferenceService $preferences,
    ) {}

    /**
     * Sends the weekly community digest to all eligible reporters.
     *
     * Returns the number of notifications actually created.
     * Returns 0 without creating any notification when no relevant activity exists.
     */
    public function sendWeeklyDigest(?CarbonInterface $now = null): int
    {
        $now = $now ?? Carbon::now();
        $from = $now->copy()->subDays(7)->startOfDay();
        $to = $now->copy()->endOfDay();

        try {
            $tickets = $this->query->topActiveTickets($from, $to);

            if ($tickets->isEmpty()) {
                return 0;
            }

            $count = $tickets->count();
            $periodFrom = $from->toDateString();
            $periodTo = $to->toDateString();
            $url = route('reporter.community', ['sort' => 'active']);

            $created = 0;

            foreach ($this->recipients() as $reporter) {
                if (! $this->preferences->enabled($reporter, CommunityNotificationPreference::TYPE_DIGEST_WEEKLY)) {
                    continue;
                }

                $dedupKey = "community-digest-weekly:{$periodFrom}:{$periodTo}:{$reporter->id}";

                if ($this->alreadySent($reporter->id, $dedupKey)) {
                    continue;
                }

                $this->notifications->notifyUser($reporter, new NotificationPayload(
                    type: CommunityNotificationPreference::TYPE_DIGEST_WEEKLY,
                    title: 'Resumen semanal de Comunidad',
                    body: "Hay {$count} ".($count === 1 ? 'reporte activo destacado' : 'reportes activos destacados').' en Comunidad esta semana.',
                    url: $url,
                    icon: '📌',
                    ticketId: null,
                    dedupKey: $dedupKey,
                ));

                $created++;
            }

            return $created;
        } catch (Throwable $e) {
            Log::error('Error enviando digest semanal de Comunidad.', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * Returns all active reporters eligible to receive the digest.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return User::role('reporter')->get();
    }

    /**
     * Checks if a digest notification was already delivered for this dedup key
     * regardless of the creation date (full idempotency across days for same period).
     */
    private function alreadySent(string $userId, string $dedupKey): bool
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('dedup_key', $dedupKey)
            ->exists();
    }
}
