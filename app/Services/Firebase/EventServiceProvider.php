<?php

namespace App\Services\Firebase;

use App\Events\DuplicateDetected;
use App\Events\TicketCreated;
use App\Events\TicketResolved;
use App\Events\TicketStateChanged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(TicketCreated::class, \App\Listeners\GenerateEmbeddingOnTicketCreated::class);
        Event::listen(TicketCreated::class, \App\Listeners\DetectDuplicatesOnEmbeddingReady::class);
        Event::listen(TicketCreated::class, \App\Listeners\SendPushNotificationOnTicketCreated::class);
        Event::listen(DuplicateDetected::class, \App\Listeners\NotifyDuplicateDetected::class);
        Event::listen(TicketResolved::class, \App\Listeners\UpdateRecurrenceOnTicketResolved::class);
        Event::listen(TicketStateChanged::class, \App\Listeners\SendPushNotificationOnTicketStateChanged::class);
        Event::listen(JobFailed::class, \App\Listeners\ReportFailedQueueJob::class);
    }
}
