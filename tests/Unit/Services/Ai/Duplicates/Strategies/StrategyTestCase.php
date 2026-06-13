<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Models\Ticket;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Base class for Strategy unit tests.
 *
 * Provides factory methods to create in-memory Ticket stubs and
 * DuplicateCandidateContext instances without touching the database.
 */
abstract class StrategyTestCase extends TestCase
{
    /** Create an in-memory Ticket stub (no DB hit). */
    protected function makeTicket(array $attributes = []): Ticket
    {
        // Separate fillable attributes from non-fillable ones.
        // created_at and assigned_to are NOT in Ticket::$fillable, so they
        // must be set directly after construction.
        $fillableKeys = [
            'id', 'title', 'description', 'state',
            'location_id', 'category_id', 'priority',
            'assignment_locked', 'assignment_source',
        ];

        $base = array_merge([
            'id' => 'ticket-'.uniqid(),
            'title' => 'Default title',
            'description' => 'Default description',
            'state' => Ticket::STATE_OPEN,
            'location_id' => 'loc-aaa',
            'category_id' => 'cat-aaa',
        ], array_intersect_key($attributes, array_flip($fillableKeys)));

        $ticket = new Ticket($base);

        // Force-set non-fillable attributes directly.
        if (array_key_exists('created_at', $attributes)) {
            $value = $attributes['created_at'];
            // Explicitly allow null (means "no created_at", AgeHours = null)
            $ticket->created_at = ($value === null) ? null : (($value instanceof Carbon) ? $value : Carbon::parse($value));
        }

        if (array_key_exists('assigned_to', $attributes)) {
            $ticket->setAttribute('assigned_to', $attributes['assigned_to']);
        }

        return $ticket;
    }

    /**
     * Create a context with two in-memory tickets and an optional similarity.
     *
     * @param  array<string, mixed>  $ticketAttrs
     * @param  array<string, mixed>  $candidateAttrs
     */
    protected function makeContext(
        array $ticketAttrs = [],
        array $candidateAttrs = [],
        ?float $similarity = 0.95,
        ?Carbon $now = null,
    ): DuplicateCandidateContext {
        return new DuplicateCandidateContext(
            ticket: $this->makeTicket($ticketAttrs),
            candidate: $this->makeTicket($candidateAttrs),
            now: $now ?? Carbon::now(),
            embeddingSimilarity: $similarity,
        );
    }
}
