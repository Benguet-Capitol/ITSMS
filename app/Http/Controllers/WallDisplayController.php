<?php

namespace App\Http\Controllers;

use App\Services\WallDisplayService;
use Illuminate\Http\JsonResponse;

/**
 * The IT office's wall display (/display on the web app). Read-only; the
 * route needs display.view, the only permission the Wall Display role has.
 */
class WallDisplayController extends Controller
{
    public function show(WallDisplayService $display): JsonResponse
    {
        return response()->json($display->summary());
    }
}
