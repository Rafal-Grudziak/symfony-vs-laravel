import http from 'k6/http';
import { check } from 'k6';

import {
  benchmarkHeaders,
  defaultThresholds,
  getBaseUrl,
  getDuration,
  getProjectMaxId,
  getTaskMaxId,
  getVus,
  randomInt,
} from './lib/config.js';
import { recordResponse } from './lib/metrics.js';
import { makeHandleSummary } from './lib/summary.js';

export const handleSummary = makeHandleSummary('01-crud');

export const options = {
  scenarios: {
    crud: {
      executor: 'constant-vus',
      vus: getVus(),
      duration: getDuration(),
    },
  },
  thresholds: defaultThresholds(),
};

/**
 * Mixed workload: list + show (read), and POST→PUT→DELETE cycles (write) so the DB
 * stays balanced without deleting seeded rows.
 */
export default function main() {
  const base = getBaseUrl();
  const params = { headers: benchmarkHeaders() };
  const taskMax = getTaskMaxId();
  const projMax = getProjectMaxId();
  const roll = Math.random();

  if (roll < 0.42) {
    const res = http.get(`${base}/api/tasks`, params);
    recordResponse(res);
    check(res, { 'GET /api/tasks 200': (r) => r.status === 200 });
  } else if (roll < 0.78) {
    const id = randomInt(1, taskMax);
    const res = http.get(`${base}/api/tasks/${id}`, params);
    recordResponse(res);
    check(res, { 'GET /api/tasks/{id} 2xx': (r) => r.status === 200 || r.status === 404 });
  } else {
    const body = JSON.stringify({
      project_id: randomInt(1, projMax),
      title: `k6-${__VU}-${__ITER}-${Date.now()}`,
      description: 'k6 crud benchmark',
      status: 'todo',
      priority: 'medium',
    });
    const res = http.post(`${base}/api/tasks`, body, params);
    recordResponse(res);
    if (res.status !== 201) {
      check(res, { 'POST /api/tasks 201': (r) => r.status === 201 });
      return;
    }
    let id;
    try {
      id = JSON.parse(res.body).data.id;
    } catch {
      return;
    }
    const putBody = JSON.stringify({
      title: `k6-upd-${id}`,
      status: 'in_progress',
    });
    const res2 = http.put(`${base}/api/tasks/${id}`, putBody, params);
    recordResponse(res2);
    check(res2, { 'PUT /api/tasks/{id} 200': (r) => r.status === 200 });
    const res3 = http.del(`${base}/api/tasks/${id}`, params);
    recordResponse(res3);
    check(res3, { 'DELETE /api/tasks/{id} 204': (r) => r.status === 204 });
  }
}
