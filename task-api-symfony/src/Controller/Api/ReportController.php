<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\ReportService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports')]
final class ReportController
{
    public function __construct(
        private readonly ReportService $reports,
    ) {
    }

    #[Route('/tasks-per-project', name: 'api_reports_tasks_per_project', methods: ['GET'])]
    public function tasksPerProject(): JsonResponse
    {
        return new JsonResponse(['data' => $this->reports->tasksPerProject()]);
    }

    #[Route('/top-projects', name: 'api_reports_top_projects', methods: ['GET'])]
    public function topProjects(): JsonResponse
    {
        return new JsonResponse(['data' => $this->reports->topProjects(10)]);
    }

    #[Route('/complex-task-overview', name: 'api_reports_complex_task_overview', methods: ['GET'])]
    public function complexTaskOverview(Request $request): JsonResponse
    {
        $limit = min(200, max(1, (int) $request->query->get('limit', 50)));
        $rows = $this->reports->complexTaskOverview($limit);

        return new JsonResponse(['data' => $rows]);
    }
}
