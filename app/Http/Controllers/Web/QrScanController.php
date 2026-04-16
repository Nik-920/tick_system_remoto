<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;

class QrScanController extends Controller
{
    public function show(string $token): RedirectResponse
    {
        if (! preg_match('/^[A-Za-z0-9_-]{6,128}$/', $token)) {
            abort(404);
        }

        $location = Location::query()
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->first();

        if ($location === null) {
            abort(404);
        }

        return redirect()
            ->route('tickets.create', ['location_id' => $location->id])
            ->with('status', 'Ubicacion detectada desde QR: ' . $location->name);
    }
}
