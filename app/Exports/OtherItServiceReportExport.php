<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class OtherItServiceReportExport implements WithMultipleSheets
{
    public function __construct(private array $summary) {}

    public function sheets(): array
    {
        return [
            new OtherItServiceReportStatusSheet($this->summary),
            new OtherItServiceReportBreakdownSheet('By Month', 'Month', $this->summary['by_month']),
            new OtherItServiceReportBreakdownSheet('By Office', 'Office/Department', $this->summary['by_office']),
            new OtherItServiceReportBreakdownSheet('By Personnel', 'Assigned Personnel', $this->summary['by_personnel']),
        ];
    }
}

class OtherItServiceReportStatusSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(private array $summary) {}

    public function array(): array
    {
        $rows = [
            ['Total Requests Received', $this->summary['total']],
        ];

        foreach ($this->summary['by_status'] as $status => $count) {
            $rows[] = [ucfirst(str_replace('_', ' ', $status)), $count];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Metric', 'Count'];
    }

    public function title(): string
    {
        return 'Summary';
    }
}

class OtherItServiceReportBreakdownSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private string $sheetTitle,
        private string $labelHeading,
        private $breakdown,
    ) {}

    public function array(): array
    {
        $rows = [];

        foreach ($this->breakdown as $label => $count) {
            $rows[] = [$label, $count];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [$this->labelHeading, 'Count'];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }
}
