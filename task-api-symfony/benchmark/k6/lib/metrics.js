import { Counter, Trend } from 'k6/metrics';

/** Application-reported SQL statement count (when X-Benchmark-Metrics: 1). */
export const xQueryCount = new Trend('x_query_count', true);

/** Application-reported wall time in ms (header X-Response-Time-Ms). */
export const xResponseTimeMs = new Trend('x_response_time_ms', true);

const statusCounters = {};
const tracked = [200, 201, 204, 301, 302, 400, 401, 403, 404, 422, 429, 500, 502, 503];

for (const code of tracked) {
  statusCounters[code] = new Counter(`http_status_${code}`);
}

const statusOther = new Counter('http_status_other');

/**
 * Record optional benchmark response headers and HTTP status bucket.
 */
export function recordResponse(res) {
  const sc = res.status;
  if (statusCounters[sc]) {
    statusCounters[sc].add(1);
  } else {
    statusOther.add(1);
  }

  const qc = res.headers['X-Query-Count'] || res.headers['X-Query-count'];
  if (qc !== undefined && qc !== '') {
    const n = parseInt(String(qc), 10);
    if (!Number.isNaN(n)) {
      xQueryCount.add(n);
    }
  }

  const rt = res.headers['X-Response-Time-Ms'] || res.headers['X-Response-time-ms'];
  if (rt !== undefined && rt !== '') {
    const n = parseFloat(String(rt));
    if (!Number.isNaN(n)) {
      xResponseTimeMs.add(n);
    }
  }
}
