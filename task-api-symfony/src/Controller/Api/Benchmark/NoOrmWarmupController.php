<?php

declare(strict_types=1);

namespace App\Controller\Api\Benchmark;

use App\Benchmark\NoOrm\NoOrmTaskCatalog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/benchmark/no-orm/warmup', name: 'api_benchmark_no_orm_warmup', methods: ['POST'])]
final class NoOrmWarmupController
{
    public function __construct(
        private readonly NoOrmTaskCatalog $catalog,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $this->catalog->warm();

        return new JsonResponse([
            'data' => [
                'warmed' => true,
                'catalog_ready' => $this->catalog->isWarmed(),
            ],
        ]);
    }
}
