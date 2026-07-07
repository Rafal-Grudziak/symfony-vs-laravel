<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function tasksPerProject(): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->tasksPerProject(),
        ]);
    }

    public function topProjects(): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->topProjects(10),
        ]);
    }

    /**
     * Optional heavier read path for benchmarking nested eager loads and counts.
     */
    public function complexTaskOverview(Request $request): JsonResponse
    {
        $limit = min(200, max(1, (int) $request->query('limit', 50)));

        return TaskResource::collection($this->reports->complexTaskOverview($limit))->response();
    }
}
