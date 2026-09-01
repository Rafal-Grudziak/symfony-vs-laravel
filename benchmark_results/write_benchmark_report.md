# Write operations benchmark report

- **Started:** 2026-08-27 11:31:10 CEST
- **Finished:** 2026-08-27 11:32:23 CEST
- **Laravel:** `http://localhost:8080`
- **Symfony:** `http://localhost:8081`
- **Warm-up requests per framework/scenario:** 5
- **Measured requests per framework/scenario:** 30
- **Project ID used for creates:** 1
- **HTTP timeout (s):** 30

## Notes

- Status values used in payloads: `todo` (create), `done` (update) — matching API enums (`todo|in_progress|done|cancelled`).
- Update/delete iterations create a helper task first; only the PUT/DELETE is measured.
- Tasks created during `create_task` are deleted after the scenario (outside measured time).

## Scenarios

### create_task

POST /api/tasks — tworzenie nowego zadania

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 5.104 | 15.950 |
| Median response time (ms) | 4.334 | 12.846 |
| Min response time (ms) | 3.974 | 10.875 |
| Max response time (ms) | 16.311 | 35.090 |
| Stddev response time (ms) | 2.515 | 7.444 |
| P90 response time (ms) | 5.169 | 31.657 |
| P95 response time (ms) | 10.573 | 33.922 |
| Avg SQL queries | 3.000 | 3.000 |
| Min SQL queries | 3.000 | 3.000 |
| Max SQL queries | 3.000 | 3.000 |
| Avg response size (bytes) | 304.7 | 630.7 |

- Faster framework (lower avg ms): **laravel** (68.0% lower mean)
- SQL query avg difference (Laravel − Symfony): **0.000**
- Mean response time ratio (Laravel / Symfony): **0.320**

### update_task

PUT /api/tasks/{id} — aktualizacja istniejącego zadania

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 5.055 | 15.428 |
| Median response time (ms) | 4.851 | 12.372 |
| Min response time (ms) | 4.612 | 10.760 |
| Max response time (ms) | 7.708 | 32.766 |
| Stddev response time (ms) | 0.645 | 5.961 |
| P90 response time (ms) | 5.527 | 23.395 |
| P95 response time (ms) | 6.631 | 28.518 |
| Avg SQL queries | 5.000 | 3.000 |
| Min SQL queries | 5.000 | 3.000 |
| Max SQL queries | 5.000 | 3.000 |
| Avg response size (bytes) | 310.7 | 310.7 |

- Faster framework (lower avg ms): **laravel** (67.2% lower mean)
- SQL query avg difference (Laravel − Symfony): **2.000**
- Mean response time ratio (Laravel / Symfony): **0.328**

### delete_task

DELETE /api/tasks/{id} — usuwanie zadania

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 3.171 | 5.940 |
| Median response time (ms) | 2.937 | 5.781 |
| Min response time (ms) | 2.618 | 5.035 |
| Max response time (ms) | 6.251 | 7.900 |
| Stddev response time (ms) | 0.823 | 0.707 |
| P90 response time (ms) | 3.156 | 6.662 |
| P95 response time (ms) | 5.478 | 7.759 |
| Avg SQL queries | 2.000 | 2.000 |
| Min SQL queries | 2.000 | 2.000 |
| Max SQL queries | 2.000 | 2.000 |
| Avg response size (bytes) | 0.0 | 0.0 |

- Faster framework (lower avg ms): **laravel** (46.6% lower mean)
- SQL query avg difference (Laravel − Symfony): **0.000**
- Mean response time ratio (Laravel / Symfony): **0.534**

## Summary

| Scenario | Laravel avg | Symfony avg | Laravel median | Symfony median | Laravel P95 | Symfony P95 | Laravel SQL | Symfony SQL | Faster | Diff % |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| create_task | 5.104 | 15.950 | 4.334 | 12.846 | 10.573 | 33.922 | 3.000 | 3.000 | laravel | 68.0% |
| update_task | 5.055 | 15.428 | 4.851 | 12.372 | 6.631 | 28.518 | 5.000 | 3.000 | laravel | 67.2% |
| delete_task | 3.171 | 5.940 | 2.937 | 5.781 | 5.478 | 7.759 | 2.000 | 2.000 | laravel | 46.6% |

## Artifacts

- Raw results: `/Users/rafal/code/symfony-vs-laravel/write_benchmark_results.csv`
- Summary CSV: `/Users/rafal/code/symfony-vs-laravel/write_benchmark_summary.csv`
- This report: `/Users/rafal/code/symfony-vs-laravel/write_benchmark_report.md`
