<?php

namespace App\Providers;

use App\Contracts\Ai\EmbeddingProvider;
use App\Contracts\Notifications\EmailNotificationProvider;
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
use App\Services\Notifications\NullEmailNotificationProvider;
use App\Services\Resend\ResendEmailNotificationAdapter;
use BladeUI\Icons\Factory as BladeIconsFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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

        // En tests (runningUnitTests()) se liga un no-op para que los flujos que
        // disparan eventos de ticket reales no llamen a la API de Resend; los
        // tests dedicados de email inyectan FakeEmailNotificationProvider
        // directamente en el listener/servicio, sin pasar por el contenedor.
        $this->app->bind(
            EmailNotificationProvider::class,
            $this->app->runningUnitTests()
                ? NullEmailNotificationProvider::class
                : ResendEmailNotificationAdapter::class
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
        // blade-lucide-icons registers its "lucide" icon set via a callAfterResolving
        // callback that fires when BladeUI\Icons\Factory is first resolved. In Docker,
        // bootstrap/providers.php providers (including this one) load before package
        // providers, so our own callAfterResolving(Factory) would fire before the lucide
        // set is added — resulting in icons registered with no prefix. Using app->booted()
        // defers until after all providers have registered AND booted, guaranteeing the
        // lucide set exists when registerComponents() runs.
        $this->app->booted(function () {
            try {
                $this->app->make(BladeIconsFactory::class)->registerComponents();
            } catch (\Throwable) {
                // Silently skip if blade-icons is not installed or not yet bound.
            }
        });

        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        $this->configureRateLimiters();
        $this->guardAgainstSqliteFallbackInProtectedEnvironments();
        $this->listenForSlowQueries();
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

        // Reporte QR público (invitados): navegación del formulario/seguimiento.
        RateLimiter::for('public-reports', function (Request $request): Limit {
            return Limit::perMinute(30)->by($this->rateLimitKey($request));
        });

        // Reporte QR público (invitados): envío del formulario — agresivo por IP.
        RateLimiter::for('public-report-submissions', function (Request $request): Limit {
            return Limit::perMinute(5)->by($this->rateLimitKey($request));
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

    private function listenForSlowQueries(): void
    {
        if (! app()->environment('local') || ! config('app.log_slow_queries', false)) {
            return;
        }

        $threshold = (int) config('app.slow_query_ms', 100);

        DB::listen(function (object $query) use ($threshold): void {
            /** @var QueryExecuted $query */
            if ($query->time < $threshold) {
                return;
            }

            Log::channel('single')->warning('Slow query detected', [
                'time_ms' => $query->time,
                'sql' => $query->sql,
                'bindings_count' => count($query->bindings),
            ]);
        });
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
