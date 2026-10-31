<?php

namespace App\Console\Commands;

use App\Models\CalibrationTest;
use App\Services\ReportedCalibrationValueService;
use Illuminate\Console\Command;

class BackfillReportedCalibrationValues extends Command
{
    protected $signature = 'calibration:backfill-reported-values {--dry-run : Tampilkan jumlah tanpa menyimpan}';

    protected $description = 'Isi nilai correction dan acceptable limit laporan yang masih kosong';

    public function handle(ReportedCalibrationValueService $values): int
    {
        $updated = 0;
        $skipped = 0;

        CalibrationTest::with(['instrument.acceptableLimit', 'items'])->chunkById(200, function ($tests) use ($values, &$updated, &$skipped) {
            foreach ($tests as $test) {
                $data = [];
                if (blank($test->reported_correction)) {
                    $data['reported_correction'] = $values->correction($test);
                }
                if (blank($test->reported_acceptable_limit)) {
                    $data['reported_acceptable_limit'] = $values->acceptableLimit($test);
                }

                if (! $data) {
                    $skipped++;

                    continue;
                }
                if (! $this->option('dry-run')) {
                    $test->update($data);
                }
                $updated++;
            }
        });

        $prefix = $this->option('dry-run') ? 'Akan diperbarui' : 'Berhasil diperbarui';
        $this->info("{$prefix}: {$updated}; dilewati karena sudah terisi: {$skipped}.");

        return self::SUCCESS;
    }
}
