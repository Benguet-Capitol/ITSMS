<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Profile;

class ProfileController extends Controller
{
    public function updateStatus(Request $request) {
        $user = auth('sanctum')->user();

        if ($user && $user->profile) {
            $user->profile->update([
                'status' => Profile::STATUS_ONLINE,
                'last_seen_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Heartbeat updated']);
    }

    public function setStatusOffline(Request $request) {
        $user = auth('sanctum')->user();

        if ($user && $user->profile) {
            $user->profile->update([
                'status' => Profile::STATUS_OFFLINE,
                'engagement' => null, // 🔑 clear BUSY engagement
                'last_seen_at' => now(),
            ]);
            return response()->json(['message' => 'Status set to offline.']);
        }

        return response()->noContent(); // 204 if no user
    }
}
