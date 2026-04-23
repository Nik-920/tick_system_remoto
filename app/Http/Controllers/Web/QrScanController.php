<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Http\RedirectResponse;

class QrScanController extends Controller
{
    public function show(string $token, TicketQrLogger $logger): RedirectResponse
    {
        $actorId = request()->user()?->id;

        if (! preg_match('/^[A-Za-z0-9_-]{6,128}$/', $token)) {
            $logger->warning('qr.scan.invalid_token', [
                'actor_id' => $actorId,
                'qr_token' => $token,
            ]);

            abort(404);
        }

        $location = Location::query()
            ->where('qr_token', $token)
            ->active()
            ->first();

        if ($location === null) {
            $logger->warning('qr.scan.location_not_found', [
                'actor_id' => $actorId,
                'qr_token' => $token,
            ]);

            abort(404);
        }

        $logger->info('qr.scan.redirected', [
            'actor_id' => $actorId,
            'location_id' => $location->id,
            'qr_token' => $token,
        ]);

        return redirect()
            ->route('tickets.create', ['location_id' => $location->id])
            ->with('status', 'Ubicacion detectada desde QR: ' . $location->name);
    }
}
