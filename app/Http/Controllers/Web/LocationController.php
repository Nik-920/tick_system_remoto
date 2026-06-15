<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListLocationsRequest;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Jobs\GenerateLocationQrImage;
use App\Models\Location;
use App\Services\Locations\LocationSimilarityService;
use App\Services\Qr\QrTokenService;
use App\Services\Storage\LocationQrStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class LocationController extends Controller
{
    public function index(ListLocationsRequest $request): View
    {
        $this->authorize('create', Location::class);

        $filters = $request->validated();
        $query = Location::query()->withCount(['tickets', 'incidentHistory']);

        $this->applyFilters($query, $filters);

        $locations = $query
            ->latest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return view('locations.index', [
            'locations' => $locations,
            'filters' => $filters,
            'metrics' => $this->buildMetrics(),
            'qrStats' => $this->buildQrStats(),
            'activity' => [], // TODO: replace with real audit-log query
            'topIncidents' => $this->buildTopIncidents(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Location::class);

        return view('locations.create');
    }

    public function store(
        StoreLocationRequest $request,
        QrTokenService $qrTokenService,
        LocationSimilarityService $similarityService
    ): RedirectResponse {
        $this->authorize('create', Location::class);

        $data = $request->validated();

        $similarLocations = $similarityService->findSimilar($data);
        if ($similarLocations->isNotEmpty() && ! $request->boolean('confirm_similar_location')) {
            return redirect()
                ->back()
                ->withInput()
                ->with([
                    'similar_locations_warning' => $similarityService->formatSimilarLocations($similarLocations),
                    'confirmation_required' => true,
                ]);
        }

        $location = Location::query()->create([
            'name' => (string) $data['name'],
            'building' => (string) $data['building'],
            'floor' => $data['floor'] ?? null,
            'room_code' => (string) $data['room_code'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'qr_token' => $qrTokenService->generateUniqueToken(),
            'qr_image_url' => null,
            'qr_generation_status' => 'pending',
            'qr_last_error' => null,
            'qr_job_id' => null,
            'qr_generated_at' => null,
        ]);

        $this->dispatchQrGeneration($location, (string) $request->attributes->get('correlation_id', ''));

        return redirect()
            ->route('locations.edit', $location)
            ->with('status', 'Ubicacion creada correctamente. La imagen QR se generara en background.');
    }

    public function edit(Location $location): View
    {
        $this->authorize('update', $location);

        $location->loadCount(['tickets', 'incidentHistory']);

        return view('locations.edit', [
            'location' => $location,
        ]);
    }

    public function update(
        UpdateLocationRequest $request,
        Location $location,
        LocationSimilarityService $similarityService
    ): RedirectResponse {
        $this->authorize('update', $location);

        $data = $request->validated();

        $similarityPayload = [
            'name' => $data['name'] ?? $location->name,
            'building' => $data['building'] ?? $location->building,
            'floor' => array_key_exists('floor', $data) ? $data['floor'] : $location->floor,
            'room_code' => array_key_exists('room_code', $data) ? $data['room_code'] : $location->room_code,
        ];

        $similarLocations = $similarityService->findSimilar($similarityPayload, $location->id);
        if ($similarLocations->isNotEmpty() && ! $request->boolean('confirm_similar_location')) {
            return redirect()
                ->back()
                ->withInput()
                ->with([
                    'similar_locations_warning' => $similarityService->formatSimilarLocations($similarLocations),
                    'confirmation_required' => true,
                ]);
        }

        $location->fill($data);
        $location->save();

        return redirect()
            ->route('locations.edit', $location)
            ->with('status', 'Ubicacion actualizada correctamente.');
    }

    public function destroy(Location $location, LocationQrStorageService $qrStorageService): RedirectResponse
    {
        $this->authorize('delete', $location);

        $ticketsCount = $location->tickets()->count();
        $incidentHistoryCount = $location->incidentHistory()->count();

        if ($ticketsCount > 0 || $incidentHistoryCount > 0) {
            return redirect()
                ->route('locations.edit', $location)
                ->with('error', 'No se puede eliminar la ubicacion porque tiene tickets o historial de incidencias asociados.');
        }

        $locationId = $location->id;
        $qrImageUrl = is_string($location->qr_image_url) ? $location->qr_image_url : null;

        DB::transaction(function () use ($location): void {
            $location->delete();
        });

        try {
            $qrStorageService->deleteQrImage($qrImageUrl);
        } catch (Throwable $exception) {
            Log::warning('No fue posible eliminar la imagen QR de la ubicacion en storage.', [
                'location_id' => $locationId,
                'qr_image_url' => $qrImageUrl,
                'error' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('locations.index')
            ->with('status', 'Ubicacion eliminada correctamente.');
    }

    public function regenerateQr(Request $request, Location $location, QrTokenService $qrTokenService): RedirectResponse
    {
        $this->authorize('update', $location);

        if ($location->qr_token === null || $location->qr_token === '') {
            $location->forceFill([
                'qr_token' => $qrTokenService->generateUniqueToken(),
            ])->save();
        }

        $this->dispatchQrGeneration($location, (string) $request->attributes->get('correlation_id', ''));

        return redirect()
            ->route('locations.edit', $location)
            ->with('status', 'Regeneracion de QR encolada correctamente.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Builder<Location>  $query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (($filters['is_active'] ?? null) !== null) {
            $query->withActiveState((bool) $filters['is_active']);
        }

        if (! empty($filters['building'])) {
            $query->where('building', $filters['building']);
        }

        if (! empty($filters['floor'])) {
            $query->where('floor', $filters['floor']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('room_code', 'like', "%{$search}%")
                    ->orWhere('building', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['qr_status'])) {
            $query->where('qr_generation_status', (string) $filters['qr_status']);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function buildMetrics(): array
    {
        $total = (int) Location::query()->count();
        $active = (int) Location::query()->withActiveState(true)->count();
        $qrReady = (int) Location::query()->where('qr_generation_status', 'ready')->count();
        $qrPending = (int) Location::query()->whereIn('qr_generation_status', ['pending', 'processing'])->count();
        $qrFailed = (int) Location::query()->where('qr_generation_status', 'failed')->count();
        $activePct = $total > 0 ? round((float) $active / (float) $total * 100.0, 1) : 0.0;

        return [
            ['label' => 'Ubicaciones', 'sub' => 'Registradas',              'value' => $total,     'icon' => 'map-pin', 'bg' => 'bg-blue-50',   'fg' => 'text-blue-600'],
            ['label' => 'Activas',     'sub' => "{$activePct}% del total",  'value' => $active,    'icon' => 'check',   'bg' => 'bg-green-50',  'fg' => 'text-green-600'],
            ['label' => 'QR generados', 'sub' => 'Disponibles',              'value' => $qrReady,   'icon' => 'qr',      'bg' => 'bg-blue-50',   'fg' => 'text-blue-600'],
            ['label' => 'Pendientes',  'sub' => 'Esperando QR',             'value' => $qrPending, 'icon' => 'clock',   'bg' => 'bg-orange-50', 'fg' => 'text-orange-500'],
            ['label' => 'Error en QR', 'sub' => 'Requieren revisión',       'value' => $qrFailed,  'icon' => 'alert',   'bg' => 'bg-red-50',    'fg' => 'text-red-500'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function buildQrStats(): array
    {
        $rawTotal = (int) Location::query()->count();
        $total = $rawTotal > 0 ? $rawTotal : 1;
        $ready = (int) Location::query()->where('qr_generation_status', 'ready')->count();
        $pending = (int) Location::query()->whereIn('qr_generation_status', ['pending', 'processing'])->count();
        $failed = (int) Location::query()->where('qr_generation_status', 'failed')->count();
        $none = max(0, $total - $ready - $pending - $failed);

        return [
            ['label' => 'Generados',  'value' => $ready,   'pct' => round((float) $ready / (float) $total * 100.0, 1), 'color' => '#22c55e'],
            ['label' => 'Pendientes', 'value' => $pending, 'pct' => round((float) $pending / (float) $total * 100.0, 1), 'color' => '#f97316'],
            ['label' => 'Error',      'value' => $failed,  'pct' => round((float) $failed / (float) $total * 100.0, 1), 'color' => '#ef4444'],
            ['label' => 'No aplica',  'value' => $none,    'pct' => round((float) $none / (float) $total * 100.0, 1), 'color' => '#d1d5db'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function buildTopIncidents(): array
    {
        // TODO: replace with real query joining tickets/incident_history when reporting indices are ready
        $colors = ['text-red-500', 'text-red-500', 'text-orange-500', 'text-orange-500', 'text-blue-600'];

        return Location::query()
            ->whereHas('tickets')
            ->withCount('tickets as tickets_count')
            ->orderByDesc('tickets_count')
            ->limit(5)
            ->get(['id', 'name', 'room_code'])
            ->map(static function (Location $loc, int $idx) use ($colors): array {
                return [
                    'code' => $loc->room_code,
                    'name' => $loc->name,
                    'count' => (int) $loc->tickets_count,
                    'color' => $colors[$idx] ?? 'text-blue-600',
                ];
            })
            ->all();
    }

    private function dispatchQrGeneration(Location $location, string $correlationId = ''): void
    {
        $jobId = (string) Str::uuid();

        $location->forceFill([
            'qr_generation_status' => 'pending',
            'qr_last_error' => null,
            'qr_job_id' => $jobId,
            'qr_generated_at' => null,
        ])->save();

        GenerateLocationQrImage::dispatch($location->id, $jobId, $correlationId);
    }
}
