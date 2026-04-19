<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\LocationPolicy;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        $this->guardAgainstSqliteFallbackInProtectedEnvironments();
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
