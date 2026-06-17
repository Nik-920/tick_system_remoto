<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardDateRangeRequest;
use App\Models\User;
use App\Queries\Dashboard\MaintenanceReportQuery;
use App\ViewModels\Dashboard\MaintenanceReportViewModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Maintenance self-report: an HTML preview and a PDF download of the
 * professional maintenance report for the selected date range.
 *
 * The report is built by its own dedicated layer (MaintenanceReportQuery →
 * MaintenanceReportViewModel), separate from the live dashboard layer
 * (MaintenanceDashboardQuery/ViewModel), which keeps working unchanged.
 *
 * Authorization is enforced by the route middleware (auth + role:maintenance).
 * The report is always scoped to the authenticated technician; there is no
 * technician selector and no way to request another user's data from these
 * endpoints.
 */
class MaintenanceDashboardReportController extends Controller
{
    public function preview(DashboardDateRangeRequest $request): View
    {
        return view('reports.maintenance.professional-report', [
            'vm' => $this->viewModel($request),
        ]);
    }

    public function pdf(DashboardDateRangeRequest $request): Response
    {
        $viewModel = $this->viewModel($request);

        $pdf = Pdf::loadView('reports.maintenance.professional-report-pdf', ['vm' => $viewModel])
            ->setPaper('a4');

        $filename = sprintf(
            'informe-mantenimiento-%s_%s.pdf',
            $viewModel->period->fromDate(),
            $viewModel->period->toDate(),
        );

        return $request->boolean('inline')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    private function viewModel(DashboardDateRangeRequest $request): MaintenanceReportViewModel
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return MaintenanceReportQuery::for($user, $request->toDateRange());
    }
}
