<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs each report or printout someone downloads (docs/activity-log.md):
 * which one, its filters, and the record it's about for single-record
 * printouts. Only successful downloads are logged.
 */
class LogReportGeneration
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->isSuccessful()) {
            return $response;
        }

        $route = $request->route();
        $subject = collect($route?->parameters() ?? [])->first(fn ($parameter) => $parameter instanceof Model);

        // "api/tickets/reports/technician-performance/pdf" -> "Tickets reports technician performance pdf"
        $report = Str::of($route?->uri() ?? $request->path())
            ->after('api/')
            ->replaceMatches('/\{[^}]+\}\/?/', '')
            ->replace(['/', '-'], ' ')
            ->squish()
            ->ucfirst()
            ->toString();

        $filters = collect($request->query())
            ->reject(fn ($value) => $value === null || $value === '' || $value === [])
            ->all();

        $this->logger->record(
            action: 'report',
            subject: $subject,
            context: array_filter([
                'report' => $report,
                'filters' => $filters ?: null,
            ]),
            label: $subject ? null : $report,
        );

        return $response;
    }
}
