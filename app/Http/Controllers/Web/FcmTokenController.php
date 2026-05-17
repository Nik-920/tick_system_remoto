<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'device' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        FcmToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $request->token,
            ],
            [
                'device' => $request->device ?? 'web',
            ]
        );

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        FcmToken::where('user_id', $request->user()->id)
            ->where('token', $request->token)
            ->delete();

        return response()->json(['success' => true]);
    }
}
