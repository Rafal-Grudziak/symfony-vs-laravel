/**
 * Shared benchmark configuration. Same scripts work against any stack
 * (Symfony or Laravel) by changing BASE_URL only — routes and JSON shapes match.
 */
export function getBaseUrl() {
  const u = __ENV.BASE_URL || 'http://localhost:8081';
  return u.replace(/\/$/, '');
}

export function getVus() {
  const n = parseInt(__ENV.VUS || '10', 10);
  return Number.isFinite(n) && n > 0 ? n : 10;
}

export function getDuration() {
  return __ENV.DURATION || '30s';
}

export function getTaskMaxId() {
  const n = parseInt(__ENV.TASK_MAX_ID || '10000', 10);
  return Number.isFinite(n) && n > 0 ? n : 10000;
}

export function getProjectMaxId() {
  const n = parseInt(__ENV.PROJECT_MAX_ID || '50', 10);
  return Number.isFinite(n) && n > 0 ? n : 50;
}

export function getBulkCount() {
  const n = parseInt(__ENV.BULK_COUNT || '50', 10);
  if (!Number.isFinite(n) || n < 1) return 50;
  return Math.min(10000, Math.max(1, n));
}

export function getResultsDir() {
  const d = __ENV.RESULTS_DIR || '../results';
  return d.replace(/\/$/, '');
}

export function benchmarkHeaders(extra = {}) {
  return Object.assign(
    {
      'X-Benchmark-Metrics': '1',
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    extra,
  );
}

export function randomInt(min, max) {
  return min + Math.floor(Math.random() * (max - min + 1));
}

export function defaultThresholds() {
  return {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<15000'],
  };
}
