import http from 'k6/http';
import { check } from 'k6';

import { benchmarkHeaders, defaultThresholds, getBaseUrl, getDuration, getVus } from './lib/config.js';
import { recordResponse } from './lib/metrics.js';
import { makeHandleSummary } from './lib/summary.js';

export const handleSummary = makeHandleSummary('03-eager-loading');

export const options = {
  scenarios: {
    eager: {
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
  const res = http.get(
    `${base}/api/tasks?with=project,comments,tags&per_page=15&page=1`,
    params,
  );
  recordResponse(res);
  check(res, { 'eager list 200': (r) => r.status === 200 });
}
