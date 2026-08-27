<?php

namespace App\Http\Controllers\Api\Benchmark;

use App\Benchmark\NoOrm\NoOrmTaskCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class NoOrmWarmupController extends Controller
{
    public function __construct(
        private readonly NoOrmTaskCatalog $catalog,
    ) {}

    public function __invoke(): JsonResponse
    {
        $this->catalog->warm();

        return response()->json([
            'data' => [
                'warmed' => true,
                'catalog_ready' => $this->catalog->isWarmed(),
            ],
        ]);
    }
}
