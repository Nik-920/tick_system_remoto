<?php

namespace App\Providers;

use App\Contracts\Ai\EmbeddingProvider;
use App\Contracts\Notifications\PushNotificationProvider;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\LocationPolicy;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use App\Services\Ai\Duplicates\DuplicateDetectionEngine;
use App\Services\Ai\Duplicates\Strategies\ActiveAssignmentStrategy;
use App\Services\Ai\Duplicates\Strategies\CandidateStateStrategy;
use App\Services\Ai\Duplicates\Strategies\ContextualDuplicateStrategy;
use App\Services\Ai\Duplicates\Strategies\EmbeddingSimilarityStrategy;
use App\Services\Ai\Duplicates\Strategies\GenericTextPenaltyStrategy;
use App\Services\Ai\Duplicates\Strategies\HistoricalRecurrenceStrategy;
use App\Services\Ai\Duplicates\Strategies\LocationIncidentPatternStrategy;
use App\Services\Ai\Duplicates\Strategies\RecurrenceGuardStrategy;
use App\Services\Ai\Duplicates\Strategies\SameCategoryStrategy;
use App\Services\Ai\Duplicates\Strategies\SameLocationStrategy;
use App\Services\Ai\Duplicates\Strategies\TimeWindowStrategy;
use App\Services\Ai\Duplicates\Strategies\TitleOverlapStrategy;
use App\Services\Ai\Duplicates\Strategies\VisionEvidenceStrategy;
use App\Services\Ai\HuggingFaceEmbeddingAdapter;
use App\Services\Firebase\FirebasePushNotificationAdapter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        EventServiceProvider::disableEventDiscovery();

        $this->app->bind(
            PushNotificationProvider::class,
            FirebasePushNotificationAdapter::class
        );

        $this->app->bind(
            EmbeddingProvider::class,
            HuggingFaceEmbeddingAdapter::class
        );

        // ── Strategy Pattern: Duplicate Detection ────────────────────────────
        // Tag all concrete strategies so the engine can receive them
        // via the 'duplicate.detection.strategies' service tag.
        // Adding a new strategy only requires registering it here.
        $this->app->tag([
            EmbeddingSimilarityStrategy::class,
            TitleOverlapStrategy::class,
            SameLocationStrategy::class,
            SameCategoryStrategy::class,
            TimeWindowStrategy::class,
            CandidateStateStrategy::class,
            RecurrenceGuardStrategy::class,
            GenericTextPenaltyStrategy::class,
            ActiveAssignmentStrategy::class,
            ContextualDuplicateStrategy::class,
            VisionEvidenceStrategy::class,
            HistoricalRecurrenceStrategy::class,
            LocationIncidentPatternStrategy::class,
        ], 'duplicate.detection.strategies');

        $this->app->bind(DuplicateDetectionEngine::class, function ($app): DuplicateDetectionEngine {
            return new DuplicateDetectionEngine(
                strategies: $app->tagged('duplicate.detection.strategies'),
                duplicateThreshold: (int) config('ai.dedup.score_threshold', 70),
            );
        });
    }

    public function boot(): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        $this->configureRateLimiters();
        $this->guardAgainstSqliteFallbackInProtectedEnvironments();
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('creations', function (Request $request): Limit {
            return Limit::perMinute(20)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('mutations', function (Request $request): Limit {
            return Limit::perMinute(30)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('auth-sensitive', function (Request $request): Limit {
            return Limit::perMinutes(15, 5)->by($this->rateLimitKey($request));
        });
    }

    private function rateLimitKey(Request $request): string
    {
        $user = $request->user();
        if ($user !== null) {
            return (string) $user->getAuthIdentifier();
        }

        return (string) $request->ip();
    }

    private function guardAgainstSqliteFallbackInProtectedEnvironments(): void
    {
        if (! app()->environment(['production', 'staging'])) {
            return;
        }

        if ((bool) config('database.allow_sqlite_in_production', false)) {
            return;
        }

        $defaultConnection = strtolower((string) config('database.default', ''));

        if ($defaultConnection !== 'sqlite') {
            return;
        }

        throw new RuntimeException('Configuracion invalida de base de datos: en production/staging se detecto fallback a sqlite. Defina DB_CONNECTION=pgsql y variables DB_* o DATABASE_URL en el entorno del despliegue.');
    }
}
