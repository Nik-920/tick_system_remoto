<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListLocationsRequest;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Jobs\GenerateLocationQrImage;
use App\Models\Location;
use App\Services\Qr\QrTokenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

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
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Location::class);

        return view('locations.create');
    }

    public function store(StoreLocationRequest $request, QrTokenService $qrTokenService): RedirectResponse
    {
        $this->authorize('create', Location::class);

        $data = $request->validated();

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

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $this->authorize('update', $location);

        $location->fill($request->validated());
        $location->save();

        return redirect()
            ->route('locations.edit', $location)
            ->with('status', 'Ubicacion actualizada correctamente.');
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
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
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
