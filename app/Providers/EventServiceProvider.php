<?php

namespace App\Providers;

use App\Events\DuplicateDetected;
use App\Events\TicketCreated;
use App\Events\TicketResolved;
use App\Listeners\DetectDuplicatesOnEmbeddingReady;
use App\Listeners\GenerateEmbeddingOnTicketCreated;
use App\Listeners\NotifyDuplicateDetected;
use App\Listeners\UpdateRecurrenceOnTicketResolved;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        TicketCreated::class => [
            GenerateEmbeddingOnTicketCreated::class,
            DetectDuplicatesOnEmbeddingReady::class,
        ],
        DuplicateDetected::class => [
            NotifyDuplicateDetected::class,
        ],
        TicketResolved::class => [
            UpdateRecurrenceOnTicketResolved::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
