<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardDateRangeRequest;
use App\Models\User;
use App\Support\Dashboard\MaintenanceDashboardV2Presenter;
use Illuminate\View\View;

/**
 * Dashboard Maintenance V2 — kept as a stable alias at /dashboard/maintenance-v2.
 *
 * Since the redesign was promoted to the official /dashboard for the maintenance
 * role (DashboardController@index), this route now renders the SAME view from
 * the SAME shared payload (MaintenanceDashboardV2Presenter), so both URLs stay
 * in sync. It is retained — not deleted — as a documented alias.
 *
 * Authorization is enforced by the route middleware (auth + role:maintenance).
 */
class MaintenanceDashboardV2Controller extends Controller
{
    public function __invoke(DashboardDateRangeRequest $request, MaintenanceDashboardV2Presenter $presenter): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('dashboard.maintenance-v2', $presenter->payload($user, $request));
    }
}
