<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Profile;

class ProfileController extends Controller
{
    public function updateStatus(Request $request) {
        $user = $request->user();

        if (!$user || !$user->profile) {
            return response()->json([
                'message' => 'User profile not found.',
            ], 404);
        }

        $user->profile->update([
            'status' => Profile::STATUS_ONLINE,
            'last_seen_at' => now(),
        ]);

        return response()->noContent();
    }

    public function setStatusOffline(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->profile) {
            return response()->json([
                'message' => 'Authenticated user profile not found.',
            ], 404);
        }

        $user->profile->update([
            'status' => Profile::STATUS_OFFLINE,
            'engagement' => null,
        ]);

        return response()->noContent();
    }
}
