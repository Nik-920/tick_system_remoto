<?php

namespace App\Listeners;

use App\Events\DuplicateDetected;
use App\Jobs\LogAiDecision;
use App\Jobs\WriteAiAuditLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Observer for DuplicateDetected.
 *
 * This listener delegates audit persistence to LogAiDecision and WriteAiAuditLog
 * jobs. It catches dispatch failures so the observer pipeline is not broken.
 */
class LogDuplicateDetectionAudit
{
    public function handle(DuplicateDetected $event): void
    {
        $ticket = $event->ticket;
        $matched = $event->matchedTicket;
        $score = $event->similarityScore;
        $correlationId = $event->correlationId;
        $threshold = (float) config('ai.dedup.similarity_threshold', 0.70);

        $inputData = [
            'ticket_id' => $ticket->id,
            'matched_ticket_id' => $matched?->id,
            'location_id' => $ticket->location_id,
            'category_id' => $ticket->category_id,
        ];
        $outputData = [
            'is_duplicate' => true,
            'similarity_score' => $score,
            'threshold' => $threshold,
        ];
        $context = [
            'matched_ticket_id' => $matched?->id,
            'similarity_score' => $score,
        ];

        $async = (bool) config('ai.automation.async_processing', true);

        try {
            if ($async) {
                LogAiDecision::dispatch(
                    $ticket,
                    'semantic_dedup_check',
                    $inputData,
                    $outputData,
                    $score,
                    'flagged_duplicate',
                    $correlationId,
                );
                WriteAiAuditLog::dispatch('Duplicate ticket detected.', $context, $ticket, 'semantic_dedup_check', $correlationId);

                return;
            }

            LogAiDecision::dispatchSync(
                $ticket,
                'semantic_dedup_check',
                $inputData,
                $outputData,
                $score,
                'flagged_duplicate',
                $correlationId,
            );
            WriteAiAuditLog::dispatchSync('Duplicate ticket detected.', $context, $ticket, 'semantic_dedup_check', $correlationId);
        } catch (Throwable $e) {
            Log::error('Error logging AI duplicate detection audit.', [
                'ticket_id' => $ticket->id,
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
