<?php

namespace App\Services\Firebase;

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
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(TicketCreated::class, GenerateEmbeddingOnTicketCreated::class);
        Event::listen(TicketCreated::class, DetectDuplicatesOnEmbeddingReady::class);
        Event::listen(TicketCreated::class, SendPushNotificationOnTicketCreated::class);
        Event::listen(DuplicateDetected::class, NotifyDuplicateDetected::class);
        Event::listen(TicketResolved::class, UpdateRecurrenceOnTicketResolved::class);
        Event::listen(TicketStateChanged::class, SendPushNotificationOnTicketStateChanged::class);
        Event::listen(JobFailed::class, ReportFailedQueueJob::class);
    }
}
