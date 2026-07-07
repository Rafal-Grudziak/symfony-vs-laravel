import http from 'k6/http';
import { check } from 'k6';

import { benchmarkHeaders, defaultThresholds, getBaseUrl, getDuration, getVus } from './lib/config.js';
import { recordResponse } from './lib/metrics.js';
import { makeHandleSummary } from './lib/summary.js';

export const handleSummary = makeHandleSummary('04-lazy-loading');

export const options = {
  scenarios: {
    lazy: {
      executor: 'constant-vus',
      vus: getVus(),
      duration: getDuration(),
    },
  },
  thresholds: defaultThresholds(),
};

/**
 * Task list without relation includes (ORM/repository loads tasks only).
 */
export default function main() {
  const base = getBaseUrl();
  const params = { headers: benchmarkHeaders() };
  const res = http.get(`${base}/api/tasks?per_page=15&page=1`, params);
  recordResponse(res);
  check(res, { 'lazy list 200': (r) => r.status === 200 });
}
