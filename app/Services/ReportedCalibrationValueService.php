<?php

namespace App\Services;

use App\Models\CalibrationTest;

class ReportedCalibrationValueService
{
    public function correction(CalibrationTest $test): ?string
    {
        if ($test->avg_correction === null) {
            return $test->status;
        }

        if (in_array($test->status, ['NG', 'SPARE', 'NA', 'SERVICE'], true)) {
            return in_array($test->status, ['SPARE', 'NA', 'SERVICE'], true) ? $test->status : 'NG';
        }

        $value = (float) $test->avg_correction;
        if ($value === 0.0) {
            return 'OK';
        }

        $unit = $this->unit($test);
        $number = rtrim(rtrim(number_format(abs($value), 4, ',', ''), '0'), ',');

        return ($value > 0 ? '+' : '-').$number.($unit ? ' '.$unit : '');
    }

    public function acceptableLimit(CalibrationTest $test): string
    {
        if ($test->min_correction_snapshot === null || $test->max_correction_snapshot === null) {
            return '-';
        }

        $min = (float) $test->min_correction_snapshot;
        $max = (float) $test->max_correction_snapshot;
        $unit = $this->unit($test);
        $suffix = $unit ? ' '.$unit : '';
        $minText = $this->number($min);
        $maxText = $this->number($max);

        if (abs($min) === abs($max)) {
            return '± '.$this->number(abs($max)).$suffix;
        }

        return $minText.' s/d '.$maxText.$suffix;
    }

    public function unit(CalibrationTest $test): ?string
    {
        $units = $test->relationLoaded('items')
            ? $test->items->pluck('unit')->filter()->unique()->values()
            : collect();

        if ($units->count() === 1) {
            return $units->first();
        }

        return $test->instrument?->acceptableLimit?->unit;
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, ',', ''), '0'), ',');
    }
}
