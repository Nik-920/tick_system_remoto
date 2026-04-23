<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListLocationsRequest;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Jobs\GenerateLocationQrImage;
use App\Models\Location;
use App\Services\Observability\TicketQrLogger;
use App\Services\Qr\QrTokenService;
use App\Services\Storage\LocationQrStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class LocationController extends Controller
{
    public function __construct(private TicketQrLogger $logger)
    {
    }

    public function index(ListLocationsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Location::class);

        $filters = $request->validated();
        $query = Location::query()->withCount(['tickets', 'incidentHistory']);

        $this->applyFilters($query, $filters);

        $locations = $query
            ->latest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return LocationResource::collection($locations);
    }

    public function show(Location $location): LocationResource
    {
        $this->authorize('view', $location);

        $location->loadCount(['tickets', 'incidentHistory']);

        return new LocationResource($location);
    }

    public function store(StoreLocationRequest $request, QrTokenService $qrTokenService): JsonResponse
    {
        $this->authorize('create', Location::class);

        $correlationId = (string) $request->attributes->get('correlation_id', '');

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

        $this->logger->info('qr.location.created', [
            'location_id' => $location->id,
            'actor_id' => $request->user()?->id,
            'correlation_id' => $correlationId,
            'is_active' => $location->is_active,
            'qr_generation_status' => $location->qr_generation_status,
        ]);

        $this->dispatchQrGeneration($location, $request->user()?->id, 'location_created', $correlationId);

        $location->refresh();

        $location->loadCount(['tickets', 'incidentHistory']);

        return response()->json([
            'message' => 'Ubicacion creada correctamente. La imagen QR se generara en background.',
            'data' => (new LocationResource($location))->resolve($request),
        ], 201);
    }

    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        $this->authorize('update', $location);

        $location->fill($request->validated());
        $location->save();

        $location->loadCount(['tickets', 'incidentHistory']);

        return response()->json([
            'message' => 'Ubicacion actualizada correctamente.',
            'data' => (new LocationResource($location))->resolve($request),
        ]);
    }

    public function destroy(Location $location, LocationQrStorageService $qrStorageService): JsonResponse
    {
        $this->authorize('delete', $location);

        $ticketsCount = $location->tickets()->count();
        $incidentHistoryCount = $location->incidentHistory()->count();

        if ($ticketsCount > 0 || $incidentHistoryCount > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la ubicacion porque tiene tickets o historial de incidencias asociados.',
                'errors' => [
                    'location' => [
                        'La ubicacion tiene datos relacionados y no puede eliminarse.',
                    ],
                ],
                'data' => [
                    'tickets_count' => $ticketsCount,
                    'incident_history_count' => $incidentHistoryCount,
                ],
            ], 409);
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

        return response()->json([
            'message' => 'Ubicacion eliminada correctamente.',
        ]);
    }

    public function regenerateQr(Location $location, QrTokenService $qrTokenService): JsonResponse
    {
        $this->authorize('update', $location);

        $correlationId = (string) request()->attributes->get('correlation_id', '');

        if ($location->qr_token === null || $location->qr_token === '') {
            $newToken = $qrTokenService->generateUniqueToken();

            $location->forceFill([
                'qr_token' => $newToken,
            ])->save();

            $this->logger->info('qr.token.regenerated', [
                'location_id' => $location->id,
                'actor_id' => request()->user()?->id,
                'correlation_id' => $correlationId,
                'qr_token' => $newToken,
            ]);
        }

        $this->dispatchQrGeneration($location, request()->user()?->id, 'manual_regenerate', $correlationId);

        $location->refresh();
        $location->loadCount(['tickets', 'incidentHistory']);

        return response()->json([
            'message' => 'Regeneracion de QR encolada correctamente.',
            'data' => (new LocationResource($location))->resolve(request()),
        ], 202);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('is_active', $filters)) {
            $query->withActiveState(
                $filters['is_active'] === null ? null : (bool) $filters['is_active']
            );
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

    private function dispatchQrGeneration(
        Location $location,
        mixed $actorId = null,
        string $trigger = 'unknown',
        string $correlationId = ''
    ): void
    {
        $jobId = (string) Str::uuid();

        $location->forceFill([
            'qr_generation_status' => 'pending',
            'qr_last_error' => null,
            'qr_job_id' => $jobId,
            'qr_generated_at' => null,
        ])->save();

        GenerateLocationQrImage::dispatch($location->id, $jobId, $correlationId);

        $this->logger->info('qr.generation.dispatched', [
            'location_id' => $location->id,
            'actor_id' => $actorId,
            'correlation_id' => $correlationId,
            'qr_job_id' => $jobId,
            'trigger' => $trigger,
            'qr_generation_status' => 'pending',
        ]);
    }
}
