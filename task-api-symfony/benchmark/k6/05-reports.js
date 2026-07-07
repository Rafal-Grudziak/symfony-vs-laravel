import http from 'k6/http';
import { check } from 'k6';

import { benchmarkHeaders, defaultThresholds, getBaseUrl, getDuration, getVus } from './lib/config.js';
import { recordResponse } from './lib/metrics.js';
import { makeHandleSummary } from './lib/summary.js';

export const handleSummary = makeHandleSummary('05-reports');

export const options = {
  scenarios: {
    reports: {
      executor: 'constant-vus',
      vus: getVus(),
      duration: getDuration(),
    },
  },
  thresholds: defaultThresholds(),
};

const paths = [
  '/api/reports/tasks-per-project',
  '/api/reports/top-projects',
  '/api/reports/complex-task-overview?limit=50',
];

export default function main() {
  const base = getBaseUrl();
  const path = paths[Math.floor(Math.random() * paths.length)];
  const params = { headers: benchmarkHeaders() };
  const res = http.get(`${base}${path}`, params);
  recordResponse(res);
  check(res, { 'report 200': (r) => r.status === 200 });
}
