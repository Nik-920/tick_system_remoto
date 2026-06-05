<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardDateRangeRequest;
use App\Models\User;
use App\Queries\Dashboard\MaintenanceDashboardQuery;
use App\ViewModels\Dashboard\MaintenanceDashboardViewModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Maintenance self-report: an HTML preview and a PDF download of the dashboard
 * for the selected date range. Both render from the same ViewModel as the web
 * dashboard, so the report always reflects exactly what the technician sees.
 *
 * Authorization is enforced by the route middleware (auth + role:maintenance).
 * The report is always scoped to the authenticated technician; there is no way
 * to request another user's data from these endpoints.
 */
class MaintenanceDashboardReportController extends Controller
{
    public function preview(DashboardDateRangeRequest $request): View
    {
        return view('dashboard.maintenance-report', [
            'vm' => $this->viewModel($request),
        ]);
    }

    public function pdf(DashboardDateRangeRequest $request): Response
    {
        $viewModel = $this->viewModel($request);

        $pdf = Pdf::loadView('dashboard.maintenance-report-pdf', ['vm' => $viewModel])
            ->setPaper('a4');

        $filename = sprintf(
            'informe-mantenimiento-%s_%s.pdf',
            $viewModel->range->fromDate(),
            $viewModel->range->toDate(),
        );

        return $request->boolean('inline')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    private function viewModel(DashboardDateRangeRequest $request): MaintenanceDashboardViewModel
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return MaintenanceDashboardQuery::for($user, $request->toDateRange());
    }
}
