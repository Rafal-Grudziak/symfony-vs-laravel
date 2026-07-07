import http from 'k6/http';
import { check } from 'k6';

import {
  benchmarkHeaders,
  defaultThresholds,
  getBaseUrl,
  getBulkCount,
  getDuration,
  getProjectMaxId,
  getTaskMaxId,
  getVus,
  randomInt,
} from './lib/config.js';
import { recordResponse } from './lib/metrics.js';
import { makeHandleSummary } from './lib/summary.js';

export const handleSummary = makeHandleSummary('06-bulk-inserts');

export const options = {
  scenarios: {
    bulk: {
      executor: 'constant-vus',
      vus: getVus(),
      duration: getDuration(),
    },
  },
  thresholds: defaultThresholds(),
};

export default function main() {
  const base = getBaseUrl();
  const params = { headers: benchmarkHeaders() };
  const count = getBulkCount();
  const taskMax = getTaskMaxId();
  const projMax = getProjectMaxId();

  if (Math.random() < 0.5) {
    const body = JSON.stringify({
      project_id: randomInt(1, projMax),
      count,
    });
    const res = http.post(`${base}/api/benchmark/bulk-tasks`, body, params);
    recordResponse(res);
    check(res, { 'bulk-tasks 201': (r) => r.status === 201 });
  } else {
    const body = JSON.stringify({
      task_id: randomInt(1, taskMax),
      count,
    });
    const res = http.post(`${base}/api/benchmark/bulk-comments`, body, params);
    recordResponse(res);
    check(res, { 'bulk-comments 201': (r) => r.status === 201 });
  }
}
