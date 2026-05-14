<?php

namespace App\Providers;

use App\Events\DuplicateDetected;
use App\Events\TicketCreated;
use App\Events\TicketResolved;
use App\Events\TicketStateChanged;
use App\Listeners\DetectDuplicatesOnEmbeddingReady;
use App\Listeners\GenerateEmbeddingOnTicketCreated;
use App\Listeners\NotifyDuplicateDetected;
use App\Listeners\ReportFailedQueueJob;
use App\Listeners\SendPushNotificationOnTicketCreated;
use App\Listeners\SendPushNotificationOnTicketStateChanged;
use App\Listeners\UpdateRecurrenceOnTicketResolved;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobFailed;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        TicketCreated::class => [
            GenerateEmbeddingOnTicketCreated::class,
            DetectDuplicatesOnEmbeddingReady::class,
            SendPushNotificationOnTicketCreated::class,
        ],
        DuplicateDetected::class => [
            NotifyDuplicateDetected::class,
        ],
        TicketResolved::class => [
            UpdateRecurrenceOnTicketResolved::class,
        ],
        TicketStateChanged::class => [
            SendPushNotificationOnTicketStateChanged::class,
        ],
        JobFailed::class => [
            ReportFailedQueueJob::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
