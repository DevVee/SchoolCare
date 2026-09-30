<?php

namespace App\Http\Controllers;

use App\Services\DashboardBrief;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /dashboard/brief: the short "today" brief for the dashboard, as JSON.
 * {"lines": [{"text", "href"}], "source": "ai"|"rules", "generated_at"}
 */
class DashboardBriefController extends Controller
{
    public function __invoke(Request $request, DashboardBrief $brief): JsonResponse
    {
        return response()->json($brief->for($request->user()));
    }
}
