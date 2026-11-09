<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use App\Support\RequestTypes;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Activity log for every changing API request (docs/activity-log.md):
 * remembers the records the route names before the controller runs, and
 * once the response is successful writes one row per record that
 * changed, named after the controller action (accept, resolve, assign...).
 * Reads (GET/HEAD) pass straight through.
 */
class LogActivity
{
    /** Resource actions get past-tense names; everything else keeps its method name. */
    private const ACTION_NAMES = [
        'store' => 'created',
        'update' => 'updated',
        'destroy' => 'deleted',
        'restore' => 'restored',
    ];

    public function __construct(private ActivityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $this->logger->begin();

        foreach ($this->subjects($request) as $model) {
            $this->logger->watch($model);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->logger->discard();

            throw $e;
        }

        if ($response->isSuccessful()) {
            $this->logger->flush($this->actionName($request));
        } else {
            $this->logger->discard();
        }

        return $response;
    }

    /** @return list<Model> */
    private function subjects(Request $request): array
    {
        $route = $request->route();

        if (! $route) {
            return [];
        }

        $models = array_values(array_filter(
            $route->parameters(),
            fn ($parameter) => $parameter instanceof Model,
        ));

        // /api/requests/{type}/{id}/... names its request by type key and id.
        $type = $route->parameter('type');
        $id = $route->parameter('id');

        if (is_string($type) && $id !== null && ($config = RequestTypes::get($type))) {
            $record = $config['model']::query()->find($id);

            if ($record) {
                $models[] = $record;
            }
        }

        return $models;
    }

    private function actionName(Request $request): string
    {
        $method = $request->route()?->getActionMethod() ?? 'updated';

        return self::ACTION_NAMES[$method] ?? Str::snake($method);
    }
}
