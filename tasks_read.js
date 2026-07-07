import http from 'k6/http';
import { check } from 'k6';

export const options = {
  vus: 100,
  duration: '30s',
};

export default function () {
  const res = http.get(
    'http://host.docker.internal:8081/api/tasks?per_page=100&with=project,comments,tags',
    {
      headers: {
        'X-Benchmark-Metrics': '1',
      },
    }
  );

  check(res, {
    'status is 200': (r) => r.status === 200,
  });
}