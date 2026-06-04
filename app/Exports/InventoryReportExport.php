<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InventoryReportExport implements WithMultipleSheets
{
    public function __construct(
        private Collection $rows,
        private array $filters = [],
        private string $generatedAt = '',
    ) {}

    public function sheets(): array
    {
        return [
            new InventoryReportMainSheet($this->rows, $this->filters, $this->generatedAt),
            new InventoryReportSummarySheet($this->rows),
        ];
    }

    public function collection()
    {
        return $this->rows->map(function ($row) {
            return [
                'property_number' => $row['property_number'],
                'employee_name' => $row['employee_name'],
                'office' => $row['office'],
                'division_section' => $row['division_section'],
                'item_type' => $row['item_type'],
                'brand_model' => $row['brand_model'],
                'child_components' => $row['child_components'],
                // 'serial_number' => $row['serial_number'],
                'status' => $row['status'],
                'date_acquired' => $row['date_acquired'],
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Property Number',
            'Employee Name',
            'Office',
            'Division / Section',
            'Item Type',
            'Brand / Model',
            'Child Components',
            // 'Serial Number',
            'Status',
            'Date Acquired',
        ];
    }
}