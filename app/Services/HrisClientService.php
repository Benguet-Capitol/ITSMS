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
                'Accept'    => 'application/json',
            ]);
    }

    private function normalizeEmployeesResponse($data): array {
        if (!is_array($data)) return [];

        if (array_key_exists('data', $data) && is_array($data['data'])) {
            $data = $data['data'];
        }

        if (is_array($data) && !array_is_list($data)) {
            if (isset($data['fullname']) || isset($data['full_name']) || isset($data['employee_id_number']) || isset($data['employee_id']) || isset($data['id'])) {
                return [$data];
            }
            return [];
        }

        return $data;
    }

    /**
     * Get all employees, cached.
     * Single cache key — no double-caching.
     */
    public function getEmployeesCached(int $minutes = 5): array {
        return Cache::remember('hris:employees:all', now()->addMinutes($minutes), function () {
            return $this->getEmployees();
        });
    }

    /**
     * Raw fetch from HRIS — no cache.
     */
    public function getEmployees(): array {
        $baseUrl = rtrim(config('services.hris.base_url'), '/');

        $resp = $this->request()->get($baseUrl . '/getEmployees');
        $resp->throw();

        return $this->normalizeEmployeesResponse($resp->json());
    }

    /**
     * Fetch with query params, cached per unique param set.
     */
    public function getEmployeesWithParams(array $params, int $minutes = 10): array {
        $baseUrl = rtrim(config('services.hris.base_url'), '/');
        $params  = array_filter($params, fn($v) => $v !== null && $v !== '');

        $cacheKey = 'hris:getEmployees:' . md5(json_encode($params));

        return Cache::remember($cacheKey, now()->addMinutes($minutes), function () use ($baseUrl, $params) {
            $resp = $this->request()->get($baseUrl . '/getEmployees', $params);
            $resp->throw();
            return $this->normalizeEmployeesResponse($resp->json());
        });
    }

    /**
     * Search employees by name or employee ID number.
     */
    public function searchEmployees(string $q, int $limit = 200): array {
        $q = trim($q);

        // Numeric ID — hit HRIS directly with filter
        if (preg_match('/^\d{6,}$/', $q)) {
            return $this->getEmployeesWithParams(['employee_id' => $q]);
        }

        // Name search — use cached full list, filter locally
        $rows   = $this->getEmployeesCached(minutes: 5);
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

    ##
    ## Offices
    ##

    /**
     * Get offices from HRIS.
     */
    public function getOffices(): array {
        $baseUrl = rtrim(config('services.hris.base_url'), '/');

        $resp = $this->request()->get($baseUrl . '/getOffices');
        $resp->throw();

        return $resp->json() ?? [];
    }

    public function getOfficesCached(int $minutes = 30): array {
        return Cache::remember('hris:offices:all', now()->addMinutes($minutes), function () {
            return $this->getOffices();
        });
    }

    public function searchOffices(string $q, int $limit = 50): array {
        $q = trim($q);

        if ($q === '') {
            return [];
        }

        $needle = mb_strtolower($q);

        return collect($this->getOfficesCached(30))
            ->filter(function ($office) use ($needle) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $office['office_code'] ?? '',
                    $office['office_desc'] ?? '',
                ])));

                return str_contains($haystack, $needle);
            })
            ->take($limit)
            ->values()
            ->all();
    }

    
}