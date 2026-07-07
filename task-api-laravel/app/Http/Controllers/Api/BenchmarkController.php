<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Benchmark\BulkCommentsRequest;
use App\Http\Requests\Benchmark\BulkInsertRequest;
use App\Services\BenchmarkDataService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BenchmarkController extends Controller
{
    public function __construct(
        private readonly BenchmarkDataService $benchmarkData,
    ) {}

    public function bulkTasks(BulkInsertRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $inserted = $this->benchmarkData->bulkInsertTasks(
            (int) $validated['project_id'],
            (int) $validated['count'],
        );

        return response()->json([
            'data' => [
                'inserted' => $inserted,
                'project_id' => (int) $validated['project_id'],
            ],
        ], Response::HTTP_CREATED);
    }

    public function bulkComments(BulkCommentsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $inserted = $this->benchmarkData->bulkInsertComments(
            (int) $validated['task_id'],
            (int) $validated['count'],
        );

        return response()->json([
            'data' => [
                'inserted' => $inserted,
                'task_id' => (int) $validated['task_id'],
            ],
        ], Response::HTTP_CREATED);
    }
}
