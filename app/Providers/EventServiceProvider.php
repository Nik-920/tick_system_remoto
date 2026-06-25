<?php

namespace App\Providers;

use App\Events\DuplicateDetected;
use App\Events\TicketAssigned;
use App\Events\TicketCommentCreated;
use App\Events\TicketCreated;
use App\Events\TicketEvidenceAdded;
use App\Events\TicketResolved;
use App\Events\TicketStateChanged;
use App\Listeners\CreateInAppNotificationOnTicketAssigned;
use App\Listeners\CreateInAppNotificationOnTicketCommentCreated;
use App\Listeners\CreateInAppNotificationOnTicketCreated;
use App\Listeners\CreateInAppNotificationOnTicketEvidenceAdded;
use App\Listeners\CreateInAppNotificationOnTicketStateChanged;
use App\Listeners\DispatchDuplicateDetectionOnTicketCreated;
use App\Listeners\GenerateEmbeddingOnTicketCreated;
use App\Listeners\InvalidateDashboardCacheOnTicketChanged;
use App\Listeners\LogDuplicateDetectionAudit;
use App\Listeners\ReportFailedQueueJob;
use App\Listeners\SendFcmPushOnTicketAssigned;
use App\Listeners\SendFcmPushOnTicketCommentCreated;
use App\Listeners\SendFcmPushOnTicketCreated;
use App\Listeners\SendFcmPushOnTicketEvidenceAdded;
use App\Listeners\SendFcmPushOnTicketStateChanged;
use App\Listeners\UpdateRecurrenceOnTicketResolved;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobFailed;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        TicketCreated::class => [
            GenerateEmbeddingOnTicketCreated::class,
            DispatchDuplicateDetectionOnTicketCreated::class,
            CreateInAppNotificationOnTicketCreated::class,
            SendFcmPushOnTicketCreated::class,
        ],
        DuplicateDetected::class => [
            LogDuplicateDetectionAudit::class,
        ],
        TicketResolved::class => [
            UpdateRecurrenceOnTicketResolved::class,
        ],
        TicketStateChanged::class => [
            CreateInAppNotificationOnTicketStateChanged::class,
            SendFcmPushOnTicketStateChanged::class,
        ],
        TicketAssigned::class => [
            CreateInAppNotificationOnTicketAssigned::class,
            SendFcmPushOnTicketAssigned::class,
        ],
        TicketEvidenceAdded::class => [
            CreateInAppNotificationOnTicketEvidenceAdded::class,
            SendFcmPushOnTicketEvidenceAdded::class,
        ],
        TicketCommentCreated::class => [
            CreateInAppNotificationOnTicketCommentCreated::class,
            SendFcmPushOnTicketCommentCreated::class,
        ],
        JobFailed::class => [
            ReportFailedQueueJob::class,
        ],
    ];

    /** @var list<class-string> */
    protected $subscribe = [
        InvalidateDashboardCacheOnTicketChanged::class,
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
