<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class MatrixExport implements FromArray, ShouldAutoSize, WithColumnWidths, WithCustomStartCell, WithEvents, WithTitle
{
    public array $rows;

    public int $year;

    public string $typeName;

    private array $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private array $statusBg = [
        'OK' => 'E8F5E9',
        'NG' => 'FFEBEE',
        'SPARE' => 'E3F2FD',
        'NA' => 'ECEFF1',
        'SERVICE' => 'FFF3E0',
    ];

    public function __construct(array $rows, int $year, string $typeName)
    {
        $this->rows = $rows;
        $this->year = $year;
        $this->typeName = $typeName;
    }

    public function title(): string
    {
        return 'Matriks '.$this->year;
    }

    public function startCell(): string
    {
        return 'A2';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, // Kode Alat
            'B' => 18, // Merk
            'C' => 16, // Kapasitas
            'D' => 24, // Lokasi
        ];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->rows as $row) {
            $line = [
                $row['code'],
                $row['brand'] ?? '—',
                $row['capacity'] ?? '—',
                $row['location'] ?? '—',
            ];
            foreach (range(1, 12) as $m) {
                $line[] = $row['next_cell'][$m]['day'] ?: '—';
                $line[] = $row['test_cell'][$m]['day'] ?: '—';
            }
            $rows[] = $line;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Landscape + fit to width
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getPageSetup()->setFitToPage(true);

                $dataCount = count($this->rows);
                $lastRow = $dataCount + 2;

                // Header row (baris 1) - bold + background + border + center
                $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(28).'1')
                    ->getFont()->setBold(true);
                $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(28).'1')
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF0F0F0');
                $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(28).'1')
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Cell info header
                $headers = ['Kode Alat', 'Merk', 'Kapasitas', 'Lokasi'];
                foreach ($headers as $i => $h) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $h);
                }
                $col = 5;
                foreach ($this->monthNames as $m) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).'1', $m.' Next');
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1).'1', $m.' Uji');
                    $col += 2;
                }

                // Border seluruh range
                $lastColLetter = Coordinate::stringFromColumnIndex(28);
                if ($lastRow >= 1) {
                    $sheet->getStyle('A1:'.$lastColLetter.$lastRow)
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);
                }

                // Warna cell status
                foreach (range(1, 12) as $mi) {
                    $dataIdx = 0;
                    foreach ($this->rows as $row) {
                        $r = $dataIdx + 2; // data mulai baris 2
                        foreach (['next_cell', 'test_cell'] as $k) {
                            $cellLetter = Coordinate::stringFromColumnIndex(4 + ($mi - 1) * 2 + ($k === 'next_cell' ? 1 : 2));
                            $status = $row[$k][$mi]['status'] ?? 'none';
                            if (isset($this->statusBg[$status]) && $status !== 'none') {
                                $sheet->getStyle($cellLetter.$r)
                                    ->getFill()
                                    ->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setARGB('FF'.$this->statusBg[$status]);
                            }
                        }
                        $dataIdx++;
                    }
                }
            },
        ];
    }
}
