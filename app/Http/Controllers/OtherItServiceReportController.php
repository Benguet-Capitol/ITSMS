<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Exports\OtherItServiceReportExport;
use App\Models\OtherItServiceRequest;
use App\Services\PdfImageService;
use App\Services\WordReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class OtherItServiceReportController extends Controller
{
    public function summary(Request $request)
    {
        Gate::authorize('requests.other_it_services.report');

        return response()->json($this->buildSummary($request));
    }

    public function exportExcel(Request $request)
    {
        Gate::authorize('requests.other_it_services.report');

        try {
            $summary = $this->buildSummary($request);
            $filename = 'Other-IT-Service-Requests-Report_'.now()->format('Y-m-d_Hi').'.xlsx';

            return Excel::download(new OtherItServiceReportExport($summary), $filename);
        } catch (\Throwable $e) {
            Log::error('Other IT service requests Excel export failed', [
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
        Gate::authorize('requests.other_it_services.report');

        try {
            $summary = $this->buildSummary($request);

            $statusRows = [['Total Requests Received', $summary['total']]];
            foreach ($summary['by_status'] as $status => $count) {
                $statusRows[] = [ucfirst(str_replace('_', ' ', $status)), $count];
            }

            $sections = [
                [
                    'heading' => 'Summary',
                    'headings' => ['Metric', 'Count'],
                    'rows' => $statusRows,
                ],
                [
                    'heading' => 'By Month',
                    'headings' => ['Month', 'Count'],
                    'rows' => collect($summary['by_month'])->map(fn ($count, $month) => [$month, $count])->values()->all(),
                ],
                [
                    'heading' => 'By Office/Department',
                    'headings' => ['Office/Department', 'Count'],
                    'rows' => collect($summary['by_office'])->map(fn ($count, $office) => [$office, $count])->values()->all(),
                ],
                [
                    'heading' => 'By Assigned Personnel',
                    'headings' => ['Assigned Personnel', 'Count'],
                    'rows' => collect($summary['by_personnel'])->map(fn ($count, $personnel) => [$personnel, $count])->values()->all(),
                ],
            ];

            $content = $wordReports->build(
                'Other IT Service Requests Report',
                $sections,
                'Generated as of '.now()->format('Y-m-d h:i A').' — Year Filter: '.$request->input('year', 'All')
            );

            $filename = 'Other-IT-Service-Requests-Report_'.now()->format('Y-m-d_Hi').'.docx';

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Content-Length' => strlen($content),
            ]);
        } catch (\Throwable $e) {
            Log::error('Other IT service requests Word export failed', [
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
        Gate::authorize('requests.other_it_services.report');

        try {
            $summary = $this->buildSummary($request);

            $pdf = Pdf::loadView('reports.other-it-service-requests-report', [
                'summary' => $summary,
                'generatedAt' => now(),
                'year' => $request->input('year', 'All'),
                ...$pdfImages->agencyLogos(),
            ])->setPaper([0, 0, 576, 936], 'landscape');

            $filename = 'Other-IT-Service-Requests-Report_'.now()->format('Y-m-d_Hi').'.pdf';

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
            Log::error('Other IT service requests PDF export failed', [
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

    private function buildSummary(Request $request): array
    {
        $query = OtherItServiceRequest::query();

        if ($request->filled('year')) {
            $query->whereYear('date_of_request', (int) $request->input('year'));
        }

        $requests = $query->with('personnel')->get();

        // Every workflow status, zero-filled, in lifecycle order.
        $byStatus = array_fill_keys(RequestStatus::values(), 0);

        foreach ($requests->groupBy(fn ($r) => $r->status->value) as $status => $group) {
            $byStatus[$status] = $group->count();
        }

        $byMonth = $requests
            ->filter(fn ($r) => $r->date_of_request)
            ->groupBy(fn ($r) => $r->date_of_request->format('Y-m'))
            ->map(fn ($group) => $group->count())
            ->sortKeys();

        $byOffice = $requests
            ->groupBy(fn ($r) => $r->department_office ?: 'Unspecified')
            ->map(fn ($group) => $group->count())
            ->sortDesc();

        // A request worked by several people counts once for each of them.
        $byPersonnel = $requests
            ->flatMap(fn ($r) => $r->personnel->isEmpty()
                ? ['Unassigned']
                : $r->personnel->pluck('display_name')->all())
            ->countBy()
            ->sortDesc();

        return [
            'total' => $requests->count(),
            'by_status' => $byStatus,
            'by_month' => $byMonth,
            'by_office' => $byOffice,
            'by_personnel' => $byPersonnel,
        ];
    }
}
