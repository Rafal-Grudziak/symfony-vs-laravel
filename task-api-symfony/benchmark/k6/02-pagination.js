import http from 'k6/http';
import { check } from 'k6';

import { benchmarkHeaders, defaultThresholds, getBaseUrl, getDuration, getVus } from './lib/config.js';
import { recordResponse } from './lib/metrics.js';
import { makeHandleSummary } from './lib/summary.js';

export const handleSummary = makeHandleSummary('02-pagination');

export const options = {
  scenarios: {
    pagination: {
      executor: 'constant-vus',
      vus: getVus(),
      duration: getDuration(),
    },
  },
  thresholds: defaultThresholds(),
};

const pages = [1, 10, 50];

export default function main() {
  const base = getBaseUrl();
  const page = pages[Math.floor(Math.random() * pages.length)];
  const params = { headers: benchmarkHeaders() };
  const res = http.get(`${base}/api/tasks?page=${page}&per_page=15`, params);
  recordResponse(res);
  check(res, {
    [`GET /api/tasks?page=${page} 200`]: (r) => r.status === 200,
  });
}
