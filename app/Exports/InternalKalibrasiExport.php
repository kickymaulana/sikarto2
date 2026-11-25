<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class InternalKalibrasiExport extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithEvents, WithHeadings
{
    public function __construct(private readonly array $rows, private readonly string $mode = 'default') {}

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($this->mode === 'type_18' && $cell->getColumn() === 'F' && $cell->getRow() > 1 && is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return $this->mode === 'type_18' ? [
            'D' => NumberFormat::FORMAT_NUMBER_00,
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'F' => NumberFormat::FORMAT_NUMBER_00,
        ] : [];
    }

    public function headings(): array
    {
        if ($this->mode === 'type_18') {
            return [
                'NO', 'KODE', 'LOKASI', "PANJANG TAPAK\n(mm)", "LEBAR TAPAK\n(mm)", "DIAMETER\n(mm)",
                'SPESIFIKASI', 'TANGGAL KALIBRASI', 'FREK. KALIBRASI', 'HASIL KALIBRASI', 'DUE DATE', 'KALIBRATOR',
            ];
        }

        if ($this->mode === 'straightness') {
            return [
                'NO.', 'KODE', 'LOKASI', 'DUDUKAN', 'TIANG KELURUSAN', 'TITIK TENGAH',
                'TGL KALIBRASI', 'FREK KALIBRASI', 'HASIL KALIBRASI', 'DUE DATE', 'KALIBRATOR',
            ];
        }

        return [
            'Kode Alat', 'Lokasi', 'Jenis Alat', 'Merek', 'Kapasitas', 'Avg Correction',
            'Reference Document', 'Acceptable Limit', 'Tgl Kalibrasi', 'Frek Kalibrasi',
            'Hasil Kalibrasi', 'Next Kalibrasi', 'Inspector/Penguji',
        ];
    }

    public function array(): array
    {
        if ($this->mode === 'type_18') {
            return array_map(fn (array $row, int $index) => [
                $index + 51,
                $row['code'] ?? '—',
                $row['location'] ?? '—',
                ($row['length'] ?? null) === null ? '—' : $row['length'],
                ($row['width'] ?? null) === null ? '—' : $row['width'],
                ($row['diameter'] ?? null) === null ? '—' : (string) $row['diameter'],
                $row['specification'] ?? '—',
                $row['test_date'] ?? '—',
                'PERBULAN',
                ($row['status'] ?? null) === 'OK' ? 'ACCEPTED' : (($row['status'] ?? null) === 'NG' ? 'NOT ACCEPTED' : ($row['status'] ?? '—')),
                $row['next_test_date'] ?? '—',
                $row['tester'] ?? '—',
            ], $this->rows, array_keys($this->rows));
        }

        if ($this->mode === 'straightness') {
            return array_map(fn (array $row, int $index) => [
                $index + 1,
                $row['code'] ?? '—',
                $row['location'] ?? '—',
                $row['dudukan'] ?? '—',
                $row['tiang_kelurusan'] ?? '—',
                $row['titik_tengah'] ?? '—',
                $row['test_date'] ?? '—',
                'PERBULAN',
                $row['status'] === 'OK' ? 'ACCEPTED' : ($row['status'] === 'NG' ? 'NOT ACCEPTED' : $row['status']),
                $row['next_test_date'] ?? '—',
                $row['tester'] ?? '—',
            ], $this->rows, array_keys($this->rows));
        }

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
            $row['tester'] ?? '—',
        ], $this->rows);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = count($this->rows) + 1;
                $lastColumn = $this->mode === 'type_18' ? 'L' : ($this->mode === 'straightness' ? 'K' : 'M');

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
