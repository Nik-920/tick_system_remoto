<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class TicketLockUnavailableException extends RuntimeException
{
    public function __construct(string $ticketId)
    {
        parent::__construct(
            "El ticket {$ticketId} está siendo modificado en este momento. Inténtelo de nuevo en unos segundos."
        );
    }
}
