<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\CommunityComment;
use App\Models\CommunityReport;
use App\Models\User;
use App\Services\Notifications\NotificationPayload;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommunityNotificationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function notifyAdminsOfReport(CommunityReport $report): void
    {
        try {
            $url = route('admin.community.moderation');
            $admins = User::role(['admin', 'super_admin'])->get();

            foreach ($admins as $admin) {
                $this->notifications->notifyUser($admin, new NotificationPayload(
                    type: 'community.report.created',
                    title: 'Nuevo reporte en Comunidad',
                    body: 'Un reporte de Comunidad requiere revisión.',
                    url: $url,
                    icon: '🛡️',
                    ticketId: $report->ticket_id,
                    dedupKey: "community-report-created:{$report->id}:{$admin->id}",
                ));
            }
        } catch (Throwable $e) {
            Log::error('Error notificando admins de nuevo reporte comunitario.', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyReporterOfReportReview(CommunityReport $report): void
    {
        if ($report->reported_by === null) {
            return;
        }

        try {
            $reporter = User::query()->find($report->reported_by);
            if ($reporter === null) {
                return;
            }

            $body = match ($report->status) {
                CommunityReport::STATUS_RESOLVED => 'Tu reporte fue revisado y marcado como resuelto.',
                CommunityReport::STATUS_DISMISSED => 'Tu reporte fue revisado y no se tomaron acciones adicionales.',
                default => 'Un reporte que enviaste en Comunidad fue revisado por el equipo.',
            };

            $this->notifications->notifyUser($reporter, new NotificationPayload(
                type: 'community.report.reviewed',
                title: 'Tu reporte fue revisado',
                body: $body,
                url: route('reporter.community'),
                icon: '✅',
                ticketId: $report->ticket_id,
                dedupKey: "community-report-reviewed:{$report->id}:{$reporter->id}",
            ));
        } catch (Throwable $e) {
            Log::error('Error notificando reporter de revisión de reporte comunitario.', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyTicketReporterOfComment(CommunityComment $comment): void
    {
        try {
            $ticket = $comment->ticket;
            if ($ticket->reporter_id === null) {
                return;
            }

            if ((string) $comment->user_id === (string) $ticket->reporter_id) {
                return;
            }

            $reporter = User::query()->find($ticket->reporter_id);
            if ($reporter === null) {
                return;
            }

            $this->notifications->notifyUser($reporter, new NotificationPayload(
                type: 'community.comment.created',
                title: 'Nuevo comentario en Comunidad',
                body: 'Hay un nuevo comentario en uno de tus reportes públicos.',
                url: route('reporter.community'),
                icon: '💬',
                ticketId: $ticket->id,
                dedupKey: "community-comment-created:{$comment->id}:{$reporter->id}",
            ));
        } catch (Throwable $e) {
            Log::error('Error notificando reporter de ticket de nuevo comentario comunitario.', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
