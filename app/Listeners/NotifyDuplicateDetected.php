<?php

namespace App\Listeners;

use App\Events\DuplicateDetected;
use App\Jobs\LogAiDecision;
use App\Jobs\WriteAiAuditLog;

class NotifyDuplicateDetected
{
    public function handle(DuplicateDetected $event): void
    {
        $ticket = $event->ticket;
        $matched = $event->matchedTicket;
        $score = $event->similarityScore;
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
        if ($async) {
            LogAiDecision::dispatch(
                $ticket,
                'semantic_dedup_check',
                $inputData,
                $outputData,
                $score,
                'flagged_duplicate'
            );
            WriteAiAuditLog::dispatch('Duplicate ticket detected.', $context, $ticket, 'semantic_dedup_check');
            return;
        }

        LogAiDecision::dispatchSync(
            $ticket,
            'semantic_dedup_check',
            $inputData,
            $outputData,
            $score,
            'flagged_duplicate'
        );
        WriteAiAuditLog::dispatchSync('Duplicate ticket detected.', $context, $ticket, 'semantic_dedup_check');
    }
}
