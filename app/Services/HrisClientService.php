<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class HrisClientService
{
    protected function request() {
        return Http::timeout(config('services.hris.timeout'))
            ->withHeaders([
                'X-API-KEY' => config('services.hris.api_key'),
                'Accept' => 'application/json',
            ]);
    }

    private function normalizeEmployeesResponse($data): array {
        if (!is_array($data)) return [];

        // unwrap { data: [...] }
        if (array_key_exists('data', $data) && is_array($data['data'])) {
            $data = $data['data'];
        }

        // if single object, wrap
        if (is_array($data) && !array_is_list($data)) {
            if (isset($data['fullname']) || isset($data['full_name']) || isset($data['employee_id_number']) || isset($data['employee_id']) || isset($data['id'])) {
                return [$data];
            }
            return [];
        }

        return $data;
    }

    public function getEmployeesCached(int $minutes = 30): array {
        return Cache::remember('hris:employees:all', now()->addMinutes($minutes), function () {
            return $this->getEmployees(); // call raw, don't double-cache
        });
    }

    public function getEmployees(): array {
        $baseUrl = rtrim(config('services.hris.base_url'), '/');

        $resp = $this->request()->get($baseUrl . '/getEmployees');
        $resp->throw();

        return $this->normalizeEmployeesResponse($resp->json());
    }

    public function getEmployeesWithParams(array $params, int $minutes = 10): array {
        $baseUrl = rtrim(config('services.hris.base_url'), '/');
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');

        $cacheKey = 'hris:getEmployees:' . md5(json_encode($params));

        return Cache::remember($cacheKey, now()->addMinutes($minutes), function () use ($baseUrl, $params) {
            $resp = $this->request()->get($baseUrl . '/getEmployees', $params);
            $resp->throw();
            return $this->normalizeEmployeesResponse($resp->json());
        });
    }

    public function searchEmployees(string $q, int $limit = 200): array {
        $q = trim($q);

        // digits => use HRIS employee_id filter
        if (preg_match('/^\d{6,}$/', $q)) {
            return $this->getEmployeesWithParams(['employee_id' => $q]);
        }

        // name => use cached full list then filter locally
        $rows = $this->getEmployeesCached(30);

        $needle = mb_strtolower($q);

        return collect($rows)
            ->filter(function ($e) use ($needle) {
                $name = mb_strtolower($e['fullname'] ?? $e['full_name'] ?? '');
                return $name !== '' && str_contains($name, $needle);
            })
            ->take($limit)
            ->values()
            ->all();
    }

    public function getOffices(): array {
        $baseUrl = rtrim(config('services.hris.base_url'), '/');

        $resp = $this->request()->get($baseUrl . '/getOffices');
        $resp->throw();

        return $resp->json() ?? [];
    }
}