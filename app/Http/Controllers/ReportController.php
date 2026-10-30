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
        $tests = $this->internalKalibrasiRows($request);

        return Inertia::render('Laporan/InternalKalibrasi', [
            'year' => (int) ($request->filled('year') ? $request->year : now()->year),
            'factoryId' => $request->filled('factory_id') ? (int) $request->factory_id : null,
            'typeId' => $request->filled('type_id') ? (int) $request->type_id : null,
            'factories' => Factory::orderBy('name')->get(['id', 'name']),
            'types' => InstrumentType::orderBy('name')->get(['id', 'name']),
            'tests' => $tests,
        ]);
    }

    public function internalKalibrasiExport(Request $request)
    {
        $year = (int) ($request->filled('year') ? $request->year : now()->year);
        $fileName = 'Internal_Kalibrasi_Record_'.$year.'.xlsx';

        return Excel::download(new InternalKalibrasiExport($this->internalKalibrasiRows($request)->all()), $fileName);
    }

    private function internalKalibrasiRows(Request $request)
    {
        $query = CalibrationTest::with([
            'instrument.department',
            'instrument.brand',
            'instrument.capacity',
            'instrument.acceptableLimit',
            'instrument.type',
        ])->whereYear('test_date', $request->filled('year') ? (int) $request->year : now()->year)
            ->orderByDesc('test_date')
            ->orderByDesc('id');

        if ($request->filled('factory_id')) {
            $query->whereHas('instrument', fn ($q) => $q->where('factory_id', (int) $request->factory_id));
        }
        if ($request->filled('type_id')) {
            $query->whereHas('instrument', fn ($q) => $q->where('instrument_type_id', (int) $request->type_id));
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

        $unit = $test->instrument?->acceptableLimit?->unit;

        return $test->min_correction_snapshot.' s/d '.$test->max_correction_snapshot.($unit ? ' '.$unit : '');
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
