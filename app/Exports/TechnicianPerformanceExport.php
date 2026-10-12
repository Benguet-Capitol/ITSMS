<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TechnicianPerformanceExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private Collection $rows,
        private string $generatedAt = '',
    ) {}

    public function collection()
    {
        return $this->rows->map(function ($row) {
            return [
                'technician' => $row['technician'],
                'total_assigned' => $row['total_assigned'],
                'resolved' => $row['resolved'],
                'pending' => $row['pending'],
                'cancelled' => $row['cancelled'],
                'avg_resolution_hours' => $row['avg_resolution_hours'],
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Technician',
            'Total Assigned',
            'Resolved',
            'Pending',
            'Cancelled',
            'Avg. Resolution Time (hrs)',
        ];
    }

    public function title(): string
    {
        return 'Technician Performance';
    }
}
