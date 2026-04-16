<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Jobs\DetectDuplicates;
use App\Services\Ai\DeduplicationService;

class DetectDuplicatesOnEmbeddingReady
{
    public function __construct(private DeduplicationService $deduplication)
    {
    }

    public function handle(TicketCreated $event): void
    {
        if (! $this->deduplication->isEnabled()) {
            return;
        }

        $async = (bool) config('ai.automation.async_processing', true);
        if ($async) {
            DetectDuplicates::dispatch($event->ticket);
            return;
        }

        DetectDuplicates::dispatchSync($event->ticket);
    }
}
