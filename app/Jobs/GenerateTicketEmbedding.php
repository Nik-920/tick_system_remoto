<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Services\Ai\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateTicketEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function handle(EmbeddingService $embeddings): void
    {
        if (! (bool) config('ai.enabled') || ! (bool) config('ai.huggingface.enabled')) {
            return;
        }

        $description = trim((string) $this->ticket->description);
        if ($description === '') {
            return;
        }

        $hash = hash('sha256', $description);
        $existing = TicketEmbedding::where('ticket_id', $this->ticket->id)->first();
        if ($existing && $existing->description_hash === $hash && is_array($existing->embedding_vector)) {
            return;
        }

        try {
            $vector = $embeddings->generate($description);
        } catch (Throwable $exception) {
            Log::warning('Failed to generate ticket embedding.', [
                'ticket_id' => $this->ticket->id,
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        TicketEmbedding::updateOrCreate(
            ['ticket_id' => $this->ticket->id],
            [
                'embedding_vector' => $vector,
                'description_hash' => $hash,
                'similarity_score' => null,
                'matched_ticket_id' => null,
                'is_duplicate' => false,
            ]
        );
    }
}
