<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TicketReportExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private Collection $rows,
        private array $filters = [],
        private string $generatedAt = '',
    ) {}

    public function collection()
    {
        return $this->rows->map(function ($row) {
            return [
                'ticket_number' => $row['ticket_number'],
                'date' => $row['date'],
                'office' => $row['office'],
                'client' => $row['client'],
                'concern' => $row['concern'],
                'category' => $row['category'],
                'technicians' => $row['technicians'],
                'query_status' => $row['query_status'],
                'request_status' => $row['request_status'],
                'accepted_at' => $row['accepted_at'],
                'resolved_at' => $row['resolved_at'],
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Ticket Number',
            'Date',
            'Office',
            'Client',
            'Concern',
            'Category',
            'Technician(s)',
            'Query Status',
            'Request Status',
            'Accepted At',
            'Resolved At',
        ];
    }

    public function title(): string
    {
        return 'Tickets';
    }
}
