<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class InventoryReportSummarySheet implements
    FromArray,
    WithTitle,
    WithStyles,
    WithColumnWidths
{
    private array $summary;

    public function __construct(private Collection $rows)
    {
        $this->summary = $rows
            ->groupBy('item_type')
            ->map(fn ($items) => $items->count())
            ->sortKeys()
            ->toArray();
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $data = [];

        $data[] = ['ITEM TYPE SUMMARY', ''];
        $data[] = ['', ''];
        $data[] = ['Item Type', 'Count'];

        foreach ($this->summary as $type => $count) {
            $data[] = [$type ?: 'Unspecified', $count];
        }

        $data[] = ['', ''];
        $data[] = ['TOTAL', array_sum($this->summary)];

        return $data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 32,
            'B' => 12,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $summaryCount = count($this->summary);
        $lastRow      = $summaryCount + 5; // title + spacer + header + rows + spacer + total

        // Title
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 13,
                'color' => ['rgb' => '1E3A5F'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DBEAFE'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Header row
        $sheet->getStyle('A3:B3')->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 10,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A5F'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '93C5FD'],
                ],
            ],
        ]);

        // Data rows
        if ($summaryCount > 0) {
            $sheet->getStyle("A4:B{$lastRow}")->applyFromArray([
                'font'    => ['size' => 10],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ]);

            $sheet->getStyle("B4:B{$lastRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Total row
            $totalRow = $lastRow;
            $sheet->getStyle("A{$totalRow}:B{$totalRow}")->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => '1E3A5F'],
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DBEAFE'],
                ],
            ]);
        }

        return [];
    }
}