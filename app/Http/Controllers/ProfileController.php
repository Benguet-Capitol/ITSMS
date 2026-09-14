<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function updateStatus(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->profile) {
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

        // engagement (ready/busy) is left untouched -- it's maintained by
        // ProfileEngagementService based on actual active tickets, and
        // nulling it here would erase the "busy" indicator for a
        // technician who still has an accepted ticket, only to have it
        // silently disappear until their next ticket action.
        $user->profile->update([
            'status' => Profile::STATUS_OFFLINE,
        ]);

        return response()->noContent();
    }

    public function markIdle(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->profile) {
            return response()->json([
                'message' => 'Authenticated user profile not found.',
            ], 404);
        }

        // engagement is deliberately left untouched -- see setStatusOffline().
        $user->profile->update([
            'status' => Profile::STATUS_IDLE,
        ]);

        return response()->noContent();
    }
}
