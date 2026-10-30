<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class InternalKalibrasiExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings
{
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return [
            'Kode Alat', 'Lokasi', 'Jenis Alat', 'Merek', 'Kapasitas', 'Avg Correction',
            'Reference Document', 'Acceptable Limit', 'Tgl Kalibrasi', 'Frek Kalibrasi',
            'Hasil Kalibrasi', 'Next Kalibrasi',
        ];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['code'] ?? '—',
            $row['location'] ?? '—',
            $row['type'] ?? '—',
            $row['brand'] ?? '—',
            $row['capacity'] ?? '—',
            $row['avg_correction'] ?? '—',
            'MDDWI-QA22',
            $row['acceptable_limit'] ?? '—',
            $row['test_date'] ?? '—',
            'PER 1 BULAN',
            $row['status'] === 'OK' ? 'Accepted' : ($row['status'] === 'NG' ? 'Not Accepted' : $row['status']),
            $row['next_test_date'] ?? '—',
        ], $this->rows);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = count($this->rows) + 1;
                $lastColumn = 'L';

                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->freezePane('A2');

                $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
                $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFFFF7ED');
                $sheet->getStyle('A1:'.$lastColumn.'1')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('F2:F'.$lastRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
