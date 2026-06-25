<?php

namespace App\Http\Controllers;

use App\Exports\InventoryReportExport;
use App\Models\Inventory;
use App\Models\ItemType;
use App\Services\HrisClientService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class InventoryReportController extends Controller
{
    public function exportExcel(Request $request, HrisClientService $hris) {
      Gate::authorize('inventories.report');
      
      try {
          $rows = $this->getReportRows($request, $hris);

          $employees = collect($hris->getEmployeesCached());
          $itemType  = ItemType::find($request->input('item_type'));
          $employee  = $employees->firstWhere('id', (int) $request->input('employee'));

          $officeDesc = null;
          $officeCode = null;

          if ($request->filled('office')) {
              $officeId = (string) $request->input('office');

              $matchedEmployee = $employees->first(
                  fn ($e) => (string) data_get($e, 'office_id') === $officeId
              );

              $officeDesc = data_get($matchedEmployee, 'office_desc');
              $officeCode = data_get($matchedEmployee, 'office_code');
          }

          $filters = [
              'item_type' => $itemType?->type ?? 'All',
              'employee'  => data_get($employee, 'fullname')
                  ?: data_get($employee, 'full_name')
                  ?: 'All',
              'office'    => $officeDesc ?: 'All',
              'status'    => $this->cleanPdfText($request->input('status')) ?: 'All',
          ];

          $generatedAt = now()->format('F d, Y h:i A');

          // Build filename
          $filenameParts = ['Inventory-Report'];

          if (!empty($officeCode)) {
              $filenameParts[] = $officeCode;
          }
          if (!empty($filters['employee']) && $filters['employee'] !== 'All') {
              $filenameParts[] = $filters['employee'];
          }
          if (!empty($filters['item_type']) && $filters['item_type'] !== 'All') {
              $filenameParts[] = $filters['item_type'];
          }
          if (!empty($filters['status']) && $filters['status'] !== 'All') {
              $filenameParts[] = $filters['status'];
          }

          $filenameParts[] = now()->format('Y-m-d_Hi');

          $filename = implode('_', array_map(
              fn ($part) => preg_replace('/[^A-Za-z0-9\-]/', '-', $part),
              $filenameParts
          )) . '.xlsx';

          return Excel::download(
              new InventoryReportExport($rows, $filters, $generatedAt),
              $filename
          );

      } catch (\Throwable $e) {
          Log::error('Inventory Excel export failed', [
              'message' => $e->getMessage(),
              'line'    => $e->getLine(),
              'file'    => $e->getFile(),
          ]);

          return response()->json([
              'message' => 'Failed to generate Excel report.',
              'error'   => $e->getMessage(),
          ], 500);
      }
    }

    public function exportPdf(Request $request, HrisClientService $hris) {
      Gate::authorize('inventories.report');
      
      try {
          $rows = $this->getReportRows($request, $hris);

          $obsoleteCount = $rows->filter(fn ($row) => $row['is_obsolete'])->count(); 

          $employees = collect($hris->getEmployeesCached());
          $itemType = ItemType::find($request->input('item_type'));
          $employee = $employees->firstWhere('id', (int) $request->input('employee'));

          $officeDesc = null;
          $officeCode = null;

          if ($request->filled('office')) {
              $officeId = (string) $request->input('office');

              $matchedEmployee = $employees
                  ->first(function ($employee) use ($officeId) {
                      return (string) data_get($employee, 'office_id') === $officeId;
                  });

              $officeDesc = data_get($matchedEmployee, 'office_desc');
              $officeCode = data_get($matchedEmployee, 'office_code');
          }

          $filters = [
              'item_type' => $itemType?->type ?? 'All',
              'employee' => data_get($employee, 'fullname')
                  ?: data_get($employee, 'full_name')
                  ?: 'All',
              'office' => $officeDesc ?: 'All',
              'status' => $this->cleanPdfText($request->input('status')) ?: 'All',
          ];

          $summary = collect($rows)
              ->groupBy('item_type')
              ->map(fn ($items) => count($items))
              ->sortKeys();

          $customPaper = [0, 0, 576, 936];

          $pdf = Pdf::loadView('reports.inventory-report', [
              'rows' => $rows,
              'filters' => $filters,
              'generatedAt' => now(),
              'obsoleteCount' => $obsoleteCount,
              'summary' => $summary,
          ])->setPaper($customPaper, 'landscape');

          while (ob_get_level() > 0) {
              ob_end_clean();
          }

          $filenameParts = ['Inventory-Report'];

          if (!empty($officeCode)) {
              $filenameParts[] = $officeCode;
          }

          if (!empty($filters['employee']) && $filters['employee'] !== 'All') {
              $filenameParts[] = $filters['employee'];
          }

          if (!empty($filters['item_type']) && $filters['item_type'] !== 'All') {
              $filenameParts[] = $filters['item_type'];
          }

          if (!empty($filters['status']) && $filters['status'] !== 'All') {
              $filenameParts[] = $filters['status'];
          }

          $filenameParts[] = now()->format('Y-m-d_Hi');

          $filename = implode('_', array_map(
              fn ($part) => preg_replace('/[^A-Za-z0-9\-]/', '-', $part),
              $filenameParts
          )) . '.pdf';

          while (ob_get_level() > 0) {
              ob_end_clean();
          }

          return $pdf->download($filename);

      } catch (\Throwable $e) {
          Log::error('Inventory PDF export failed', [
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

    private function getReportRows(Request $request, HrisClientService $hris) {
        $employeeMap = collect($hris->getEmployeesCached())
            ->filter(fn ($e) => isset($e['id']))
            ->keyBy(fn ($e) => (int) $e['id']);

        $query = Inventory::query()
            ->with([
                'item_type',
                'brand_model.brand',
                'brand_model.item_type',
                'parent_component',
                'parent_component.brand_model.brand',
                'parent_component.brand_model.item_type',
                'parent_component.item_type',
                'internal_components.brand_model.brand',
                'internal_components.brand_model.item_type',
            ]);

        if ($request->filled('item_type')) {
            $query->where('item_type_id', $request->input('item_type'));
        }

        if ($request->filled('employee')) {
            $query->where('employee_id', (int) $request->input('employee'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('office')) {
            $officeId = (string) $request->input('office');

            $employeeIds = $employeeMap
                ->filter(fn ($e) => (string) data_get($e, 'office_id') === $officeId)
                ->keys()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if (empty($employeeIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($employeeIds) {
                    $q->whereIn('employee_id', $employeeIds)
                      ->orWhereHas('parent_component', function ($q2) use ($employeeIds) {
                          $q2->whereIn('employee_id', $employeeIds);
                      });
                });
            }
        }

        return $query
          ->orderBy('property_number')
          ->get()
          ->map(function ($inventory) use ($employeeMap) {
              $employee = $employeeMap->get((int) $inventory->employee_id);

              $employeeName =
                  data_get($employee, 'fullname') ?:
                  data_get($employee, 'full_name');

              $office =
                  data_get($employee, 'office_desc') ?:
                  data_get($employee, 'office_code');

              $brandModelDisplay = $this->formatBrandModel($inventory->brand_model);

              $childComponentsDisplay = $inventory->internal_components
                  ->map(function ($component) {
                      $componentBrandModel = $this->formatBrandModel($component->brand_model);

                      $parts = [];

                      if ($componentBrandModel) {
                          $parts[] = $componentBrandModel;
                      }

                      if ($component->slot) {
                          $parts[] = 'Slot: ' . $component->slot;
                      }

                      if ($component->quantity) {
                          $parts[] = 'Qty: ' . $component->quantity;
                      }

                      if ($component->specific_serial_number) {
                          $parts[] = 'SN: ' . $component->specific_serial_number;
                      }

                      if ($component->notes) {
                          $parts[] = 'Notes: ' . $component->notes;
                      }

                      return implode(' | ', $parts);
                  })
                  ->filter()
                  ->values()
                  ->implode("\n");

              $rawDateAcquired = $inventory->date_acquired;
                  
              if (! $rawDateAcquired && $inventory->parent_component_id) {
                  $rawDateAcquired = $inventory->parent_component?->date_acquired;
              }
              
              $isObsolete = $rawDateAcquired
                  ? Carbon::parse($rawDateAcquired)->lt(now()->subYears(5))
                  : false;

              return [
                  'property_number'  => $this->cleanPdfText($inventory->property_number),
                  'employee_name'    => $this->cleanPdfText($employeeName),
                  'office'           => $this->cleanPdfText($office),
                  'division_section' => $this->cleanPdfText(
                      data_get($employee, 'division_section')
                      ?: data_get($employee, 'division')
                      ?: data_get($employee, 'section')
                      ?: data_get($employee, 'division_desc')
                      ?: data_get($employee, 'section_desc')
                  ),
                  'item_type'        => $this->cleanPdfText($inventory->item_type?->type),
                  'brand_model'      => $this->cleanPdfText($brandModelDisplay),
                  'child_components' => $this->cleanPdfText($childComponentsDisplay),
                  'serial_number'    => $this->cleanPdfText($inventory->serial_number),
                  'status'           => $this->cleanPdfText($inventory->status),
                  'date_acquired' => $this->cleanPdfText(
                      $rawDateAcquired
                          ? Carbon::parse($rawDateAcquired)->format('F d, Y')
                          : ''
                  ),
                  'is_obsolete'      => $isObsolete,
              ];
          });
    }

    private function getReportQuery(Request $request) {
        $query = Inventory::query()->with([
            'item_type',
            'brand_model',
            'parent_component',
            'parent_component.brand_model',
            'parent_component.item_type',
        ]);

        if ($request->filled('item_type')) {
            $query->where('item_type_id', $request->input('item_type'));
        }

        if ($request->filled('employee')) {
            $query->where('employee_id', (int) $request->input('employee'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('office')) {
            // office filter logic here
        }

        return $query;
    }

    private function cleanPdfText($value): string {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        $value = preg_replace('/[^\P{C}\n\r\t]+/u', '', $value);

        return trim($value);
    }

    private function formatBrandModel($brandModel): string {
        if (! $brandModel) {
            return '';
        }

        $itemType = trim((string) data_get($brandModel, 'item_type.type'));
        $specification = trim((string) data_get($brandModel, 'specification'));
        $name = trim((string) data_get($brandModel, 'name'));
        $brand = trim((string) data_get($brandModel, 'brand.name'));

        $parts = array_filter([
            $itemType,
            $specification,
            $name,
            $brand,
        ], fn ($value) => $value !== '');

        return implode(', ', $parts);
    }
}
