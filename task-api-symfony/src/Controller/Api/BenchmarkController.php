<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\BulkCommentsBody;
use App\Dto\BulkTasksBody;
use App\Http\ApiValidation;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Service\BenchmarkDataService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/benchmark')]
final class BenchmarkController
{
    public function __construct(
        private readonly BenchmarkDataService $benchmarkData,
        private readonly ValidatorInterface $validator,
        private readonly ProjectRepository $projects,
        private readonly TaskRepository $tasks,
    ) {
    }

    #[Route('/bulk-tasks', name: 'api_benchmark_bulk_tasks', methods: ['POST'])]
    public function bulkTasks(#[MapRequestPayload] BulkTasksBody $body): JsonResponse
    {
        $v = $this->validator->validate($body);
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        if ($this->projects->find($body->projectId) === null) {
            return new JsonResponse([
                'message' => 'The given data was invalid.',
                'errors' => ['project_id' => ['The selected project id is invalid.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $inserted = $this->benchmarkData->bulkInsertTasks($body->projectId, $body->count);

        return new JsonResponse([
            'data' => [
                'inserted' => $inserted,
                'project_id' => $body->projectId,
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/bulk-comments', name: 'api_benchmark_bulk_comments', methods: ['POST'])]
    public function bulkComments(#[MapRequestPayload] BulkCommentsBody $body): JsonResponse
    {
        $v = $this->validator->validate($body);
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        if ($this->tasks->find($body->taskId) === null) {
            return new JsonResponse([
                'message' => 'The given data was invalid.',
                'errors' => ['task_id' => ['The selected task id is invalid.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $inserted = $this->benchmarkData->bulkInsertComments($body->taskId, $body->count);

        return new JsonResponse([
            'data' => [
                'inserted' => $inserted,
                'task_id' => $body->taskId,
            ],
        ], Response::HTTP_CREATED);
    }
}
