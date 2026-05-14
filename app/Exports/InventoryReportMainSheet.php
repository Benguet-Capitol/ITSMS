<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\FromArray;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class InventoryReportMainSheet implements
    FromArray,
    WithTitle,
    WithStyles,
    WithColumnWidths,
    WithEvents
{
    private int $totalRows;

    public function __construct(
        private Collection $rows,
        private array $filters = [],
        private string $generatedAt = '',
    ) {
        $this->totalRows = $rows->count();
    }

    public function registerEvents(): array {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastDataRow = $this->totalRows + 5;

                for ($i = 6; $i <= $lastDataRow; $i++) {
                    // Property Number (col B)
                    $cellB = $sheet->getCell("B{$i}");
                    $cellB->setValueExplicit((string) $cellB->getValue(), DataType::TYPE_STRING);

                    // Serial Number (col H)
                    $cellH = $sheet->getCell("H{$i}");
                    $cellH->setValueExplicit((string) $cellH->getValue(), DataType::TYPE_STRING);
                }
            },
        ];
    }

    public function title(): string {
        return 'Inventory';
    }

    public function array(): array {
        $data = [];

        // Row 1: Report title
        $data[] = ['INVENTORY REPORT', '', '', '', '', '', '', '', '', ''];

        // Row 2: Filter info
        $data[] = [
            'Generated At: ' . $this->generatedAt,
            '', '', '',
            'Office: ' . ($this->filters['office'] ?? 'All'),
            '', '',
            'Status: ' . ($this->filters['status'] ?? 'All'),
            '', '',
        ];

        // Row 3: More filters
        $data[] = [
            'Item Type: ' . ($this->filters['item_type'] ?? 'All'),
            '', '', '',
            'Employee: ' . ($this->filters['employee'] ?? 'All'),
            '', '',
            'Total Records: ' . $this->totalRows,
            '', '',
        ];

        // Row 4: Empty spacer
        $data[] = ['', '', '', '', '', '', '', '', '', ''];

        // Row 5: Column headers
        $data[] = [
            'No.',
            'Property Number',
            'Actual User',
            'Division / Section',
            'Office',
            'Item Type',
            'Brand / Model',
            'Serial Number',
            'Date Acquired',
        ];

        // Data rows starting at row 6
        foreach ($this->rows->values() as $index => $row) {
            $data[] = [
                $index + 1,
                (string) ($row['property_number'] ?? ''),
                $row['employee_name'] ?? '',
                $row['division_section'] ?? '',
                $row['office'] ?? '',
                $row['item_type'] ?? '',
                $row['brand_model'] ?? '',
                (string) ($row['serial_number'] ?? ''),
                $row['date_acquired'] ?? '',
            ];
        }

        return $data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 20,
            'C' => 28,
            'D' => 28,
            'E' => 22,
            'F' => 16,
            'G' => 36,
            'H' => 22,
            'I' => 20,
        ];
    }

    public function styles(Worksheet $sheet) {
        $lastDataRow = $this->totalRows + 5; // 4 meta rows + 1 header row

        // Row 1: Title
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 14,
                'color' => ['rgb' => '1E3A5F'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DBEAFE'],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Row 2 & 3: Filter meta
        $sheet->getStyle('A2:I3')->applyFromArray([
            'font' => [
                'size'  => 9,
                'color' => ['rgb' => '4B5563'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'],
            ],
        ]);
        $sheet->mergeCells('A2:D2');
        $sheet->mergeCells('E2:G2');
        $sheet->mergeCells('H2:I2'); 
        $sheet->mergeCells('A3:D3');
        $sheet->mergeCells('E3:G3');
        $sheet->mergeCells('H3:I3'); 

        // Row 4: Spacer
        $sheet->getRowDimension(4)->setRowHeight(6);

        // Row 5: Header
        $sheet->getStyle('A5:I5')->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 9,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A5F'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '93C5FD'],
                ],
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(20);

        // Data rows
        if ($lastDataRow >= 6) {
            $sheet->getStyle("A6:I{$lastDataRow}")->applyFromArray([
                'font'      => ['size' => 9],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => false,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ]);

            // Zebra striping
            for ($i = 6; $i <= $lastDataRow; $i++) {
                if ($i % 2 === 0) {
                    $sheet->getStyle("A{$i}:I{$i}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F8FAFC'],
                        ],
                    ]);
                }
            }

            // No. column center aligned
            $sheet->getStyle("A6:A{$lastDataRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font'      => ['color' => ['rgb' => '6B7280']],
            ]);

            $sheet->getStyle("B6:B{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

            $sheet->getStyle("H6:H{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        }

        // Freeze header row
        $sheet->freezePane('A6');

        // Auto filter
        $sheet->setAutoFilter("A5:I5");

        // Row heights for data rows
        for ($i = 6; $i <= $lastDataRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(16);
        }

        return [];
    }
}