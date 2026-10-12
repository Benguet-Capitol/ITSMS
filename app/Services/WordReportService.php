<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class WordReportService
{
    /**
     *
     * @param  array<int, array{heading?: string, headings?: array<int, string>, rows?: array<int, array<int, mixed>>}>  $sections
     */
    public function build(string $title, array $sections, ?string $subtitle = null): string
    {
        $phpWord = new PhpWord();

        $docSection = $phpWord->addSection([
            'orientation' => 'landscape',
            'marginLeft' => 600,
            'marginRight' => 600,
        ]);

        $docSection->addText($title, ['bold' => true, 'size' => 16]);

        if ($subtitle) {
            $docSection->addText($subtitle, ['italic' => true, 'size' => 10]);
        }

        $docSection->addTextBreak(1);

        foreach ($sections as $sectionData) {
            if (! empty($sectionData['heading'])) {
                $docSection->addText($sectionData['heading'], ['bold' => true, 'size' => 12]);
            }

            $headings = $sectionData['headings'] ?? [];
            $rows = $sectionData['rows'] ?? [];
            $columnCount = max(count($headings), 1);

            $table = $docSection->addTable([
                'borderSize' => 6,
                'borderColor' => '999999',
                'cellMargin' => 80,
            ]);

            if (! empty($headings)) {
                $table->addRow();
                foreach ($headings as $heading) {
                    $table->addCell(9000 / $columnCount)->addText((string) $heading, ['bold' => true]);
                }
            }

            if (empty($rows)) {
                $table->addRow();
                $table->addCell(9000)->addText('No records found.');
            } else {
                foreach ($rows as $row) {
                    $table->addRow();
                    foreach ($row as $cell) {
                        $table->addCell(9000 / $columnCount)->addText((string) $cell);
                    }
                }
            }

            $docSection->addTextBreak(1);
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');

        ob_start();
        $writer->save('php://output');

        return ob_get_clean();
    }
}
