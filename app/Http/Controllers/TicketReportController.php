<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Exports\TechnicianPerformanceExport;
use App\Exports\TicketReportExport;
use App\Models\ItService;
use App\Models\Profile;
use App\Models\Ticket;
use App\Services\PdfImageService;
use App\Services\WordReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class TicketReportController extends Controller
{
    public function technicianSelect()
    {
        Gate::authorize('tickets.report');

        $technicianIds = Ticket::query()
            ->join('ticket_personnel', 'tickets.id', '=', 'ticket_personnel.ticket_id')
            ->distinct()
            ->pluck('ticket_personnel.profile_id');

        $technicians = Profile::query()
            ->whereIn('id', $technicianIds)
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        return response()->json(['data' => $technicians]);
    }

    public function exportExcel(Request $request)
    {
        Gate::authorize('tickets.report');

        try {
            $rows = $this->getReportRows($request);

            $filters = $this->describeFilters($request);
            $generatedAt = now()->format('F d, Y h:i A');

            $filename = $this->buildFilename('Ticket-Report', $filters, 'xlsx');

            return Excel::download(
                new TicketReportExport($rows, $filters, $generatedAt),
                $filename
            );
        } catch (\Throwable $e) {
            Log::error('Ticket Excel export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'message' => 'Failed to generate Excel report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportWord(Request $request, WordReportService $wordReports)
    {
        Gate::authorize('tickets.report');

        try {
            $rows = $this->getReportRows($request);

            $table = $rows->map(fn ($row) => [
                $row['ticket_number'],
                $row['date'],
                $row['office'],
                $row['client'],
                $row['concern'],
                $row['category'],
                $row['technicians'],
                $row['query_status'],
                $row['request_status'],
            ])->all();

            $filters = $this->describeFilters($request);

            $content = $wordReports->build(
                'Ticket Report',
                [[
                    'headings' => ['Ticket Number', 'Date', 'Office', 'Client', 'Concern', 'Category', 'Technician(s)', 'Query Status', 'Request Status'],
                    'rows' => $table,
                ]],
                'Office: '.$filters['office'].' — Category: '.$filters['category'].' — Technician: '.$filters['technician'].' — Status Group: '.$filters['status_group']
            );

            $filename = $this->buildFilename('Ticket-Report', $filters, 'docx');

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Content-Length' => strlen($content),
            ]);
        } catch (\Throwable $e) {
            Log::error('Ticket Word export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'message' => 'Failed to generate Word report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportPdf(Request $request, PdfImageService $pdfImages)
    {
        Gate::authorize('tickets.report');

        try {
            ini_set('memory_limit', '1024M');
            set_time_limit(300);

            $rows = $this->getReportRows($request);

            if ($rows->count() > 1000) {
                return response()->json([
                    'message' => 'PDF export is too large without filters. Please use at least one filter or export to Excel.',
                ], 422);
            }

            $filters = $this->describeFilters($request);
            $customPaper = [0, 0, 576, 936];

            $pdf = Pdf::loadView('reports.ticket-report', [
                'rows' => $rows,
                'filters' => $filters,
                'generatedAt' => now(),
                ...$pdfImages->agencyLogos(),
            ])->setPaper($customPaper, 'landscape');

            $filename = $this->buildFilename('Ticket-Report', $filters, 'pdf');

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $pdfOutput = $pdf->output();

            return response($pdfOutput, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Content-Length' => strlen($pdfOutput),
            ]);
        } catch (\Throwable $e) {
            Log::error('Ticket PDF export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function technicianPerformanceExcel(Request $request)
    {
        Gate::authorize('tickets.report');

        try {
            $rows = $this->getTechnicianPerformanceRows();
            $generatedAt = now()->format('F d, Y h:i A');
            $filename = 'Technician-Performance-Report_'.now()->format('Y-m-d_Hi').'.xlsx';

            return Excel::download(
                new TechnicianPerformanceExport($rows, $generatedAt),
                $filename
            );
        } catch (\Throwable $e) {
            Log::error('Technician performance Excel export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'message' => 'Failed to generate Excel report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function technicianPerformanceWord(Request $request, WordReportService $wordReports)
    {
        Gate::authorize('tickets.report');

        try {
            $rows = $this->getTechnicianPerformanceRows();

            $table = $rows->map(fn ($row) => [
                $row['technician'],
                $row['total_assigned'],
                $row['resolved'],
                $row['pending'],
                $row['cancelled'],
                $row['avg_resolution_hours'],
            ])->all();

            $content = $wordReports->build(
                'Technician Performance Report',
                [[
                    'headings' => ['Technician', 'Total Assigned', 'Resolved', 'Pending', 'Cancelled', 'Avg. Resolution Time (hrs)'],
                    'rows' => $table,
                ]],
                'Generated as of '.now()->format('Y-m-d h:i A')
            );

            $filename = 'Technician-Performance-Report_'.now()->format('Y-m-d_Hi').'.docx';

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Content-Length' => strlen($content),
            ]);
        } catch (\Throwable $e) {
            Log::error('Technician performance Word export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'message' => 'Failed to generate Word report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function technicianPerformancePdf(Request $request, PdfImageService $pdfImages)
    {
        Gate::authorize('tickets.report');

        try {
            $rows = $this->getTechnicianPerformanceRows();

            $pdf = Pdf::loadView('reports.technician-performance-report', [
                'rows' => $rows,
                'generatedAt' => now(),
                ...$pdfImages->agencyLogos(),
            ])->setPaper([0, 0, 576, 936], 'landscape');

            $filename = 'Technician-Performance-Report_'.now()->format('Y-m-d_Hi').'.pdf';

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $pdfOutput = $pdf->output();

            return response($pdfOutput, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Content-Length' => strlen($pdfOutput),
            ]);
        } catch (\Throwable $e) {
            Log::error('Technician performance PDF export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
	 *
     */
    private function getReportRows(Request $request)
    {
        $query = Ticket::query()
            ->with(['itService', 'personnel']);

        if ($request->filled('office_id')) {
            $query->where('office_id', (int) $request->input('office_id'));
        }

        if ($request->filled('it_service_id')) {
            $query->where('it_service_id', (int) $request->input('it_service_id'));
        }

        if ($request->filled('personnel_id')) {
            $personnelId = (int) $request->input('personnel_id');

            $query->whereHas('personnel', function ($q) use ($personnelId) {
                $q->where('profiles.id', $personnelId);
            });
        }

        if ($request->input('status_group') === 'pending') {
            $query->where('query_status', TicketStatus::Queued);
        } elseif ($request->input('status_group') === 'resolved') {
            $query->where('query_status', TicketStatus::Resolved);
        }

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Ticket $ticket) {
                return [
                    'ticket_number' => $ticket->ticket_number,
                    'date' => $ticket->date
                        ? Carbon::parse($ticket->date)->format('Y-m-d')
                        : $ticket->created_at->format('Y-m-d'),
                    'office' => $ticket->office_desc ?: '',
                    'client' => $ticket->client_name ?: $ticket->full_name ?: '',
                    'concern' => $ticket->concern,
                    'category' => $ticket->itService?->name ?: '',
                    'technicians' => $ticket->personnel->map(fn ($profile) => $profile->display_name)->filter()->implode(', '),
                    'query_status' => $ticket->query_status?->value,
                    'request_status' => $ticket->request_status?->value,
                    'accepted_at' => optional($ticket->accepted_at)->format('Y-m-d h:i A') ?: '',
                    'resolved_at' => optional($ticket->resolved_at)->format('Y-m-d h:i A') ?: '',
                ];
            });
    }

    private function getTechnicianPerformanceRows()
    {
        $technicianIds = Ticket::query()
            ->join('ticket_personnel', 'tickets.id', '=', 'ticket_personnel.ticket_id')
            ->distinct()
            ->pluck('ticket_personnel.profile_id');

        return Profile::query()
            ->whereIn('id', $technicianIds)
            ->get()
            ->map(function (Profile $technician) {
                $tickets = Ticket::query()
                    ->whereHas('personnel', function ($q) use ($technician) {
                        $q->where('profiles.id', $technician->id);
                    })
                    ->get();

                $resolved = $tickets->where('query_status', TicketStatus::Resolved);

                $avgResolutionHours = $resolved
                    ->filter(fn (Ticket $ticket) => $ticket->accepted_at && $ticket->resolved_at)
                    ->map(fn (Ticket $ticket) => $ticket->accepted_at->diffInMinutes($ticket->resolved_at) / 60)
                    ->average();

                return [
                    'technician' => $technician->display_name,
                    'total_assigned' => $tickets->count(),
                    'resolved' => $resolved->count(),
                    'pending' => $tickets->where('query_status', TicketStatus::Queued)->count(),
                    'cancelled' => $tickets->where('query_status', TicketStatus::Cancelled)->count(),
                    'avg_resolution_hours' => $avgResolutionHours ? round($avgResolutionHours, 1) : 0,
                ];
            })
            ->sortByDesc('resolved')
            ->values();
    }

    private function describeFilters(Request $request): array
    {
        $itService = $request->filled('it_service_id')
            ? ItService::find($request->input('it_service_id'))
            : null;

        $technician = $request->filled('personnel_id')
            ? Profile::find($request->input('personnel_id'))
            : null;

        return [
            'office' => $request->input('office_name') ?: 'All',
            'category' => $itService?->name ?: 'All',
            'technician' => $technician?->display_name ?: 'All',
            'status_group' => $request->input('status_group') ? ucfirst($request->input('status_group')) : 'All',
        ];
    }

    private function buildFilename(string $prefix, array $filters, string $extension): string
    {
        $filenameParts = [$prefix];

        foreach (['category', 'technician', 'status_group'] as $key) {
            if (! empty($filters[$key]) && $filters[$key] !== 'All') {
                $filenameParts[] = $filters[$key];
            }
        }

        $filenameParts[] = now()->format('Y-m-d_Hi');

        return implode('_', array_map(
            fn ($part) => preg_replace('/[^A-Za-z0-9\-]/', '-', $part),
            $filenameParts
        )).'.'.$extension;
    }
}
