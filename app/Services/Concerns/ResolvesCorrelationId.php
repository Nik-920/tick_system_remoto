<?php

namespace App\Services\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Resolves the correlation ID for observability.
 *
 * Order of resolution:
 *  1. Explicit value passed as argument.
 *  2. Request attribute 'correlation_id' (set by middleware or prior service).
 *  3. Request header 'X-Correlation-Id'.
 *  4. Fresh UUID v4 as fallback.
 *
 * Used by TicketStateService, TicketAssignmentService, TicketCreationService.
 */
trait ResolvesCorrelationId
{
    private function resolveCorrelationId(string $correlationId): string
    {
        $trimmed = trim($correlationId);
        if ($trimmed !== '') {
            return $trimmed;
        }

        return $this->resolveCorrelationIdFromRequest() ?? (string) Str::uuid();
    }

    private function resolveCorrelationIdFromRequest(): ?string
    {
        $resolved = null;
        $request = app()->bound('request') ? request() : null;

        if ($request instanceof Request) {
            $fromAttribute = trim((string) $request->attributes->get('correlation_id', ''));
            $fromHeader = trim((string) $request->headers->get('X-Correlation-Id', ''));

            if ($fromAttribute !== '') {
                $resolved = $fromAttribute;
            } elseif ($fromHeader !== '') {
                $request->attributes->set('correlation_id', $fromHeader);
                $resolved = $fromHeader;
            }
        }

        return $resolved;
    }
}
