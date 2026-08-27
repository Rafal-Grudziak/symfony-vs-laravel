<?php

declare(strict_types=1);

namespace App\Benchmark;

/**
 * Request-scoped SQL count + wall time for {@see X_BENCHMARK_METRICS_HEADER}.
 * Optional SQL dump (thesis diagnostic) via {@see X_BENCHMARK_SQL_LOG_HEADER}.
 */
final class BenchmarkMetricsCollector
{
    private bool $enabled = false;

    private bool $sqlDumpEnabled = false;

    private float $startedAt = 0.0;

    private int $sqlCount = 0;

    private string $requestId = '';

    /** @var list<array{sql: string, params: mixed}> */
    private array $queries = [];

    public function reset(): void
    {
        $this->enabled = false;
        $this->sqlDumpEnabled = false;
        $this->startedAt = 0.0;
        $this->sqlCount = 0;
        $this->requestId = '';
        $this->queries = [];
    }

    public function begin(bool $sqlDump = false): void
    {
        $this->enabled = true;
        $this->sqlDumpEnabled = $sqlDump;
        $this->startedAt = microtime(true);
        $this->sqlCount = 0;
        $this->requestId = bin2hex(random_bytes(8));
        $this->queries = [];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isSqlDumpEnabled(): bool
    {
        return $this->sqlDumpEnabled;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function incrementSqlCount(): void
    {
        if ($this->enabled) {
            ++$this->sqlCount;
        }
    }

    public function recordQuery(string $sql, mixed $params = null): void
    {
        if (!$this->enabled || !$this->sqlDumpEnabled) {
            return;
        }

        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    /**
     * @return list<array{sql: string, params: mixed}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getSqlCount(): int
    {
        return $this->sqlCount;
    }

    public function getElapsedMs(): float
    {
        if (!$this->enabled) {
            return 0.0;
        }

        return (microtime(true) - $this->startedAt) * 1000;
    }
}
