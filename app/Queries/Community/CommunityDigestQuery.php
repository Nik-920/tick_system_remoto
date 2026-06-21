<?php

declare(strict_types=1);

namespace App\Queries\Community;

use App\Models\CommunityComment;
use App\Models\CommunityReaction;
use App\Models\CommunityReport;
use App\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Selects the most-engaged public community tickets for a given period.
 *
 * Security contract:
 * - Never selects reporter_id, assigned_to, assigned_by, or any user-identifying columns.
 * - Engagement counts are aggregated; no user identity is exposed.
 * - Hidden (community_hidden_at IS NOT NULL), cancelled, and rejected tickets are excluded.
 *
 * Weekly score formula (period-bounded engagement only):
 *   interested_week  * 3
 *   + also_happens_week * 4
 *   + seen_week      * 1
 *   + saves_week     * 3
 *   + visible_comments_week * 5
 *   - pending_reports_global * 4   (global penalty — pending reports are a quality signal)
 *
 * Tie-breaker: updated_at DESC.
 */
final class CommunityDigestQuery
{
    /** @var list<string> */
    private const PUBLIC_STATES = [
        Ticket::STATE_OPEN,
        Ticket::STATE_IN_PROGRESS,
        Ticket::STATE_RESOLVED,
    ];

    /**
     * Returns the top N public community tickets ranked by weekly engagement.
     *
     * @return Collection<int, Ticket>
     */
    public function topActiveTickets(CarbonInterface $from, CarbonInterface $to, int $limit = 5): Collection
    {
        $fromStr = $from->toDateTimeString();
        $toStr = $to->toDateTimeString();

        return Ticket::query()
            ->select(['id', 'title', 'state', 'priority', 'updated_at'])
            ->where('community_visible', true)
            ->whereNull('community_hidden_at')
            ->whereIn('state', self::PUBLIC_STATES)
            ->orderByRaw(
                '(
                    (SELECT COUNT(*) FROM community_reactions
                        WHERE community_reactions.ticket_id = tickets.id
                        AND community_reactions.type = ?
                        AND community_reactions.created_at BETWEEN ? AND ?) * 3 +
                    (SELECT COUNT(*) FROM community_reactions
                        WHERE community_reactions.ticket_id = tickets.id
                        AND community_reactions.type = ?
                        AND community_reactions.created_at BETWEEN ? AND ?) * 4 +
                    (SELECT COUNT(*) FROM community_reactions
                        WHERE community_reactions.ticket_id = tickets.id
                        AND community_reactions.type = ?
                        AND community_reactions.created_at BETWEEN ? AND ?) * 1 +
                    (SELECT COUNT(*) FROM community_saves
                        WHERE community_saves.ticket_id = tickets.id
                        AND community_saves.created_at BETWEEN ? AND ?) * 3 +
                    (SELECT COUNT(*) FROM community_comments
                        WHERE community_comments.ticket_id = tickets.id
                        AND community_comments.status = ?
                        AND community_comments.created_at BETWEEN ? AND ?) * 5 -
                    (SELECT COUNT(*) FROM community_reports
                        WHERE community_reports.ticket_id = tickets.id
                        AND community_reports.comment_id IS NULL
                        AND community_reports.status = ?) * 4
                ) DESC',
                [
                    CommunityReaction::TYPE_INTERESTED, $fromStr, $toStr,
                    CommunityReaction::TYPE_ALSO_HAPPENS, $fromStr, $toStr,
                    CommunityReaction::TYPE_SEEN, $fromStr, $toStr,
                    $fromStr, $toStr,
                    CommunityComment::STATUS_VISIBLE, $fromStr, $toStr,
                    CommunityReport::STATUS_PENDING,
                ]
            )
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Returns true when at least one public community ticket exists in the period.
     */
    public function hasActivity(CarbonInterface $from, CarbonInterface $to): bool
    {
        $fromStr = $from->toDateTimeString();
        $toStr = $to->toDateTimeString();

        return Ticket::query()
            ->where('community_visible', true)
            ->whereNull('community_hidden_at')
            ->whereIn('state', self::PUBLIC_STATES)
            ->where(function ($q) use ($fromStr, $toStr): void {
                $q->whereExists(function ($sub) use ($fromStr, $toStr): void {
                    $sub->selectRaw('1')
                        ->from('community_reactions')
                        ->whereColumn('community_reactions.ticket_id', 'tickets.id')
                        ->whereBetween('community_reactions.created_at', [$fromStr, $toStr]);
                })->orWhereExists(function ($sub) use ($fromStr, $toStr): void {
                    $sub->selectRaw('1')
                        ->from('community_saves')
                        ->whereColumn('community_saves.ticket_id', 'tickets.id')
                        ->whereBetween('community_saves.created_at', [$fromStr, $toStr]);
                })->orWhereExists(function ($sub) use ($fromStr, $toStr): void {
                    $sub->selectRaw('1')
                        ->from('community_comments')
                        ->whereColumn('community_comments.ticket_id', 'tickets.id')
                        ->where('community_comments.status', CommunityComment::STATUS_VISIBLE)
                        ->whereBetween('community_comments.created_at', [$fromStr, $toStr]);
                });
            })
            ->exists();
    }
}
