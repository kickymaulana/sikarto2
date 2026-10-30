<?php

namespace App\Http\Controllers;

use App\Exports\InternalKalibrasiExport;
use App\Models\CalibrationTest;
use App\Models\Factory;
use App\Models\InstrumentType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function internalKalibrasi(Request $request)
    {
        $filters = $this->internalKalibrasiFilters($request);
        $tests = $this->internalKalibrasiRows($filters);

        return Inertia::render('Laporan/InternalKalibrasi', [
            'year' => $filters['year'],
            'month' => $filters['month'],
            'factoryId' => $filters['factory_id'],
            'typeId' => $filters['type_id'],
            'factories' => Factory::orderBy('name')->get(['id', 'name']),
            'types' => InstrumentType::orderBy('name')->get(['id', 'name']),
            'tests' => $tests,
        ]);
    }

    public function internalKalibrasiExport(Request $request)
    {
        $filters = $this->internalKalibrasiFilters($request);
        $fileName = 'Internal_Kalibrasi_Record_'.$filters['year'].($filters['month'] ? '_'.str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT) : '').'.xlsx';

        return Excel::download(new InternalKalibrasiExport($this->internalKalibrasiRows($filters)->all()), $fileName);
    }

    private function internalKalibrasiFilters(Request $request): array
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'factory_id' => ['nullable', 'integer', 'exists:factories,id'],
            'type_id' => ['nullable', 'integer', 'exists:instrument_types,id'],
        ]);

        return [
            'year' => isset($validated['year']) ? (int) $validated['year'] : now()->year,
            'month' => isset($validated['month']) ? (int) $validated['month'] : null,
            'factory_id' => isset($validated['factory_id']) ? (int) $validated['factory_id'] : null,
            'type_id' => isset($validated['type_id']) ? (int) $validated['type_id'] : null,
        ];
    }

    private function internalKalibrasiRows(array $filters)
    {
        $query = CalibrationTest::with([
            'instrument.department',
            'instrument.brand',
            'instrument.capacity',
            'instrument.acceptableLimit',
            'instrument.type',
        ])->whereYear('test_date', $filters['year'])
            ->orderByDesc('test_date')
            ->orderByDesc('id');

        if ($filters['month']) {
            $query->whereMonth('test_date', $filters['month']);
        }

        if ($filters['factory_id']) {
            $query->whereHas('instrument', fn ($q) => $q->where('factory_id', $filters['factory_id']));
        }
        if ($filters['type_id']) {
            $query->whereHas('instrument', fn ($q) => $q->where('instrument_type_id', $filters['type_id']));
        }

        return $query->get()->map(fn (CalibrationTest $test) => [
            'id' => $test->id,
            'code' => $test->instrument?->code,
            'location' => $test->instrument?->department?->name,
            'type' => $test->instrument?->type?->name,
            'brand' => $test->instrument?->brand?->name,
            'capacity' => $test->instrument?->capacity?->name,
            'avg_correction' => $test->avg_correction,
            'acceptable_limit' => $this->acceptableLimit($test),
            'test_date' => $test->test_date?->format('d M Y'),
            'status' => $test->status,
            'next_test_date' => $test->next_test_date?->format('d M Y'),
        ])->values();

    }

    private function acceptableLimit(CalibrationTest $test): ?string
    {
        if ($test->min_correction_snapshot === null || $test->max_correction_snapshot === null) {
            return null;
        }

        $min = (float) $test->min_correction_snapshot;
        $max = (float) $test->max_correction_snapshot;
        $unit = $test->instrument?->acceptableLimit?->unit;
        $suffix = $unit ? ' '.strtoupper($unit) : '';

        if (abs($min) === abs($max)) {
            return '± '.$this->formatLimitNumber(abs($max)).$suffix;
        }

        return $this->formatLimitNumber($min).' s/d '.$this->formatLimitNumber($max).$suffix;
    }

    private function formatLimitNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, ',', ''), '0'), ',');
    }

    public function index(Request $request)
    {
        $query = CalibrationTest::with(['instrument', 'instrument.type', 'instrument.factory', 'tester', 'items'])
            ->orderBy('test_date', 'desc');

        if ($request->filled('year')) {
            $query->whereYear('test_date', $request->year);
        }
        if ($request->filled('month')) {
            $query->whereMonth('test_date', $request->month);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('factory_id')) {
            $query->whereHas('instrument', fn ($q) => $q->where('factory_id', $request->factory_id));
        }

        $results = $query->limit(500)->get();

        $summary = [
            'total' => $results->count(),
            'ok' => $results->where('status', 'OK')->count(),
            'ng' => $results->where('status', 'NG')->count(),
            'spare' => $results->where('status', 'SPARE')->count(),
            'na' => $results->where('status', 'NA')->count(),
            'service' => $results->where('status', 'SERVICE')->count(),
        ];

        return Inertia::render('Reports/Index', [
            'tests' => $results,
            'filters' => $request->only(['year', 'month', 'status', 'factory_id']),
            'factories' => Factory::orderBy('name')->get(),
            'summary' => $summary,
        ]);
    }
}
