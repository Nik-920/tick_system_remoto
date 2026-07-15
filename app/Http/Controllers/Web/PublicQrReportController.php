<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\DispatchesTicketCreatedAfterResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicQrReportRequest;
use App\Models\Category;
use App\Models\Location;
use App\Services\Observability\TicketQrLogger;
use App\Services\Tickets\PublicQrReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Carril público de reporte por QR (sin login). No reemplaza nada: /scan y el
 * flujo autenticado quedan intactos. Este controller solo existe detrás del
 * flag tickets.public_qr_report.enabled (404 si está apagado).
 */
class PublicQrReportController extends Controller
{
    use DispatchesTicketCreatedAfterResponse;

    public function __construct(private TicketQrLogger $logger) {}

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $this->assertFeatureEnabled();

        // Usuario logueado → flujo normal de siempre (formulario completo).
        if ($request->user() !== null) {
            return redirect()->route('scan.show', ['token' => $token]);
        }

        $location = $this->resolveLocation($token);

        return view('public.qr-report', [
            'location' => $location,
            'token' => $token,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(
        StorePublicQrReportRequest $request,
        string $token,
        PublicQrReportService $service,
    ): RedirectResponse {
        $this->assertFeatureEnabled();

        $location = $this->resolveLocation($token);

        // Honeypot lleno = bot. Respondemos como si hubiera funcionado para no
        // darle señal, pero no se crea nada.
        if (trim((string) $request->input('website', '')) !== '') {
            $this->logger->warning('qr.public_report.honeypot', ['location_id' => $location->id]);

            return redirect()->route('public.qr.success');
        }

        $correlationId = (string) Str::uuid();

        $mediaFiles = [];
        $photo = $request->file('photo');
        if ($photo !== null) {
            $mediaFiles[] = $photo;
        }

        $result = $service->createGuestTicket(
            (string) $location->id,
            [
                'category_id' => (string) $request->validated('category_id'),
                'description' => (string) $request->validated('description'),
                'contact_email' => $request->validated('contact_email'),
            ],
            $mediaFiles,
            $correlationId,
        );

        // Mismo patrón que el flujo autenticado: el evento se despacha después
        // de la respuesta → notificaciones, dedup y auto-asignación corren igual.
        $this->dispatchAfterResponse($result['ticket'], $correlationId);

        $this->logger->info('qr.public_report.created', [
            'ticket_id' => $result['ticket']->id,
            'location_id' => $location->id,
            'correlation_id' => $correlationId,
        ]);

        return redirect()
            ->route('public.qr.success')
            ->with('tracking_code', $result['tracking_code']);
    }

    public function success(): View|RedirectResponse
    {
        $this->assertFeatureEnabled();

        if (! session()->has('tracking_code')) {
            return redirect('/');
        }

        return view('public.qr-report-success', [
            'trackingCode' => (string) session('tracking_code'),
        ]);
    }

    public function track(Request $request, PublicQrReportService $service): View
    {
        $this->assertFeatureEnabled();

        $code = (string) $request->query('code', '');
        $contact = $code !== '' ? $service->findByTrackingCode($code) : null;

        return view('public.qr-report-track', [
            'code' => $code,
            'contact' => $contact,
            'searched' => $code !== '',
            'stateLabels' => [
                'open' => 'Abierto',
                'in_progress' => 'En atención',
                'resolved' => 'Resuelto',
                'rejected' => 'Rechazado',
                'cancelled' => 'Cancelado',
            ],
        ]);
    }

    private function assertFeatureEnabled(): void
    {
        abort_unless((bool) config('tickets.public_qr_report.enabled', false), 404);
    }

    private function resolveLocation(string $token): Location
    {
        if (! preg_match('/^[A-Za-z0-9_-]{6,128}$/', $token)) {
            $this->logger->warning('qr.public_report.invalid_token', ['qr_token' => $token]);

            abort(404);
        }

        $location = Location::query()
            ->where('qr_token', $token)
            ->active()
            ->first();

        if ($location === null) {
            $this->logger->warning('qr.public_report.location_not_found', ['qr_token' => $token]);

            abort(404);
        }

        return $location;
    }
}
