<?php

namespace App\Http\Controllers;

use App\Models\CalibrationTest;
use App\Models\Instrument;
use App\Services\ReportedCalibrationValueService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TestController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'in:20,50,100'],
        ]);

        $query = CalibrationTest::with(['instrument', 'instrument.type', 'instrument.factory', 'tester'])
            ->orderBy('test_date', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->whereHas('instrument', fn ($q) => $q->where('code', 'like', "%{$request->search}%"));
        }

        return Inertia::render('Tests/Index', [
            'tests' => $query->paginate($request->integer('per_page', 20))->withQueryString(),
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Tests/Create', [
            'instruments' => [],
        ]);
    }

    public function searchInstruments(Request $request)
    {
        $data = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $search = $data['search'];
        $instruments = Instrument::query()
            ->where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('type', fn ($type) => $type->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', "%{$search}%"));
            })
            ->orderBy('code')
            ->limit(20)
            ->get(['id', 'code']);

        return response()->json(['data' => $instruments]);
    }

    public function instrument(Instrument $instrument)
    {
        abort_unless($instrument->is_active, 404);

        return response()->json(['data' => $this->instrumentPayload($instrument)]);
    }

    private function instrumentPayload(Instrument $instrument): array
    {
        $instrument->load([
            'capacity:id,name',
            'capacity.groups:id,capacity_id,name,reference_media,sort_order',
            'capacity.groups.standards:id,standard_group_id,standard_value,sort_order',
            'factory:id,name',
            'department:id,name',
            'type:id,name',
            'brand:id,name',
            'acceptableLimit:id,name,min_correction,max_correction,unit',
            'specification:id,name,length_min,length_max,width_min,width_max,diameter_min,diameter_max,dimension_unit',
        ]);

        return [
            'id' => $instrument->id,
            'code' => $instrument->code,
            'factory' => $instrument->factory ? ['name' => $instrument->factory->name] : null,
            'department' => $instrument->department ? ['name' => $instrument->department->name] : null,
            'type' => $instrument->type ? ['name' => $instrument->type->name] : null,
            'brand' => $instrument->brand ? ['name' => $instrument->brand->name] : null,
            'capacity' => $instrument->capacity ? [
                'name' => $instrument->capacity->name,
                'groups' => $instrument->capacity->groups->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'reference_media' => $group->reference_media,
                    'standards' => $group->standards->map(fn ($standard) => [
                        'id' => $standard->id,
                        'standard_value' => $standard->standard_value,
                    ])->all(),
                ])->all(),
            ] : null,
            'acceptable_limit' => $instrument->acceptableLimit ? [
                'name' => $instrument->acceptableLimit->name,
                'min_correction' => $instrument->acceptableLimit->min_correction,
                'max_correction' => $instrument->acceptableLimit->max_correction,
                'unit' => $instrument->acceptableLimit->unit,
            ] : null,
            'specification' => $instrument->specification ? [
                'name' => $instrument->specification->name,
                'length_min' => $instrument->specification->length_min,
                'length_max' => $instrument->specification->length_max,
                'width_min' => $instrument->specification->width_min,
                'width_max' => $instrument->specification->width_max,
                'diameter_min' => $instrument->specification->diameter_min,
                'diameter_max' => $instrument->specification->diameter_max,
                'dimension_unit' => $instrument->specification->dimension_unit,
            ] : null,
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'instrument_id' => 'required|integer|exists:instruments,id',
            'test_date' => 'required|date|before_or_equal:today',
            'status' => 'nullable|in:OK,NG,SPARE,NA,SERVICE',
            'selected_group_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);
        $selectedStatus = $data['status'] ?? null;
        $isManual = in_array($selectedStatus, ['SPARE', 'NA', 'SERVICE'], true);
        $items = $isManual ? [] : $request->validate([
            'items' => 'nullable|array',
            'items.*.standard_template_id' => 'required|integer|distinct',
            'items.*.reading_value' => 'required|numeric|decimal:0,4|between:-99999999.9999,99999999.9999',
        ])['items'] ?? [];
        $dimensionChecks = $isManual ? [] : $request->validate([
            'dimension_checks' => 'nullable|array',
            'dimension_checks.*.dimension' => 'required|string|distinct|in:length,width,diameter',
            'dimension_checks.*.measured_value' => 'required|numeric|decimal:0,4|between:-99999999.9999,99999999.9999',
        ])['dimension_checks'] ?? [];

        $test = DB::transaction(function () use ($request, $data, $selectedStatus, $isManual, $items, $dimensionChecks) {
            $instrument = Instrument::with(['acceptableLimit', 'capacity.groups.standards', 'specification'])->lockForUpdate()->findOrFail($data['instrument_id']);
            $limit = $instrument->acceptableLimit;
            $computedStatus = null;
            $avgCorrection = null;
            $testItems = [];
            $testDimensionChecks = [];
            $selectedGroup = null;

            if (! $isManual) {
                $groups = $instrument->capacity?->groups ?? collect();
                if ($groups->isNotEmpty()) {
                    $selectedGroup = $groups->firstWhere('id', $request->integer('selected_group_id'));
                    if ($groups->count() === 1) {
                        $selectedGroup = $groups->first();
                    } elseif (! $request->has('selected_group_id')) {
                        $selectedGroup = null;
                    } elseif (! $selectedGroup) {
                        throw ValidationException::withMessages(['selected_group_id' => 'Pilih grup penentu rata-rata.']);
                    }
                }
                $templates = $groups->flatMap(fn ($group) => $group->standards);
                $expectedIds = $templates->pluck('id')->sort()->values()->all();
                $submittedIds = collect($items)->pluck('standard_template_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
                if ($expectedIds !== $submittedIds || $groups->contains(fn ($group) => $group->standards->isEmpty())) {
                    throw ValidationException::withMessages(['items' => 'Isi semua titik uji kapasitas tepat satu kali tanpa titik tambahan.']);
                }
                if (empty($expectedIds) && empty($dimensionChecks)) {
                    throw ValidationException::withMessages(['items' => 'Alat harus memiliki titik uji kapasitas atau pemeriksaan ukuran.']);
                }
                if (empty($expectedIds) && ! in_array($selectedStatus, ['OK', 'NG'], true)) {
                    throw ValidationException::withMessages(['status' => 'Pilih status OK atau NG untuk pengujian ukuran.']);
                }
                if ($expectedIds && ! $limit) {
                    throw ValidationException::withMessages(['instrument_id' => 'Batas toleransi alat tidak tersedia.']);
                }
                if ($expectedIds) {
                    Validator::make($limit->toArray(), [
                        'min_correction' => 'required|numeric|decimal:0,4|between:-99999999.9999,99999999.9999',
                        'max_correction' => 'required|numeric|decimal:0,4|between:-99999999.9999,99999999.9999|gte:min_correction',
                        'unit' => 'required|string|max:20',
                    ])->validate();
                }
                $readings = collect($items)->keyBy('standard_template_id');
                foreach ($groups as $groupOrder => $group) {
                    foreach ($group->standards as $pointOrder => $standard) {
                        Validator::make(['standard_value' => $standard->standard_value], [
                            'standard_value' => 'required|numeric|decimal:0,4|between:-99999999.9999,99999999.9999',
                        ])->validate();
                        $reading = $readings[$standard->id]['reading_value'];
                        $correction = round((float) $reading - (float) $standard->standard_value, 4);
                        if (! is_finite($correction) || abs($correction) > 99999999.9999) {
                            throw ValidationException::withMessages(['items' => 'Koreksi melampaui rentang penyimpanan.']);
                        }
                        $testItems[] = [
                            'standard_value' => $standard->standard_value,
                            'reading_value' => $reading,
                            'correction' => $correction,
                            'is_within_limit' => $limit->isWithin($correction),
                            'group_order' => $groupOrder,
                            'group_name' => $group->name,
                            'reference_media' => $group->reference_media,
                            'unit' => $limit->unit,
                            'point_order' => $pointOrder,
                        ];
                    }
                }
                $scoringItems = $selectedGroup
                    ? array_values(array_filter($testItems, fn ($item) => $item['group_order'] === $groups->search(fn ($group) => $group->id === $selectedGroup->id)))
                    : $testItems;
                if ($scoringItems) {
                    $avgCorrection = round(array_sum(array_column($scoringItems, 'correction')) / count($scoringItems), 4);
                    $computedStatus = $limit->isWithin($avgCorrection) ? 'OK' : 'NG';
                }

                $dimensions = collect([
                    'length' => ['label' => 'Panjang', 'min' => $instrument->specification?->length_min, 'max' => $instrument->specification?->length_max],
                    'width' => ['label' => 'Lebar', 'min' => $instrument->specification?->width_min, 'max' => $instrument->specification?->width_max],
                    'diameter' => ['label' => 'Diameter', 'min' => $instrument->specification?->diameter_min, 'max' => $instrument->specification?->diameter_max],
                ])->filter(fn ($dimension) => $dimension['min'] !== null && $dimension['max'] !== null);
                $expectedDimensions = $dimensions->keys()->sort()->values()->all();
                $submittedDimensions = collect($dimensionChecks)->pluck('dimension')->sort()->values()->all();
                if ($expectedDimensions !== $submittedDimensions) {
                    throw ValidationException::withMessages(['dimension_checks' => 'Isi semua ukuran spesifikasi tepat satu kali tanpa ukuran tambahan.']);
                }
                if ($dimensions->isNotEmpty() && empty($instrument->specification?->dimension_unit)) {
                    throw ValidationException::withMessages(['instrument_id' => 'Satuan ukuran spesifikasi tidak tersedia.']);
                }
                $measurements = collect($dimensionChecks)->keyBy('dimension');
                foreach ($dimensions as $dimensionKey => $dimension) {
                    $key = $dimensions->keys()->search($dimensionKey);
                    $measured = $measurements[$dimensionKey]['measured_value'];
                    $testDimensionChecks[] = [
                        'dimension' => $dimensionKey,
                        'label' => $dimension['label'],
                        'min_value' => $dimension['min'],
                        'max_value' => $dimension['max'],
                        'measured_value' => $measured,
                        'unit' => $instrument->specification->dimension_unit,
                        'is_within_range' => (float) $measured >= (float) $dimension['min'] && (float) $measured <= (float) $dimension['max'],
                        'sort_order' => $key,
                    ];
                }
            }

            $testDate = Carbon::parse($data['test_date']);
            $test = CalibrationTest::create([
                'instrument_id' => $instrument->id,
                'test_date' => $testDate->toDateString(),
                'next_test_date' => $testDate->addMonth()->toDateString(),
                'tester_id' => $request->user()->id,
                'status' => $selectedStatus ?? $computedStatus,
                'selected_status' => $selectedStatus,
                'computed_status' => $computedStatus,
                'avg_correction' => $avgCorrection,
                'min_correction_snapshot' => $limit?->min_correction,
                'max_correction_snapshot' => $limit?->max_correction,
                'selected_group_id' => $selectedGroup?->id,
                'selected_group_name' => $selectedGroup?->name,
                'selected_group_reference_media' => $selectedGroup?->reference_media,
                'selected_group_order' => $selectedGroup ? $groups->search(fn ($group) => $group->id === $selectedGroup->id) : null,
                'notes' => $data['notes'] ?? null,
            ]);
            $test->items()->createMany($testItems);
            $test->dimensionChecks()->createMany($testDimensionChecks);
            $test->load(['instrument.acceptableLimit', 'items']);
            $reportedValues = app(ReportedCalibrationValueService::class);
            $test->update([
                'reported_correction' => $reportedValues->correction($test),
                'reported_acceptable_limit' => $reportedValues->acceptableLimit($test),
            ]);

            return $test;
        });

        return redirect()->route('tests.show', $test)
            ->with('flash', ['success' => "Pengujian tersimpan. Status: {$test->status}"]);
    }

    public function edit(CalibrationTest $test)
    {
        $test->load(['instrument.capacity.groups', 'items', 'dimensionChecks']);

        return Inertia::render('Tests/Edit', [
            'test' => $test,
            'groups' => $test->instrument?->capacity?->groups ?? [],
        ]);
    }

    public function update(Request $request, CalibrationTest $test)
    {
        $data = $request->validate([
            'test_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['nullable', 'in:OK,NG,SPARE,NA,SERVICE'],
            'selected_group_id' => ['nullable', 'integer'],
            'reported_correction' => ['nullable', 'string', 'max:100'],
            'reported_acceptable_limit' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.reading_value' => ['required', 'numeric', 'decimal:0,4', 'between:-99999999.9999,99999999.9999'],
            'dimension_checks' => ['nullable', 'array'],
            'dimension_checks.*.id' => ['required', 'integer', 'distinct'],
            'dimension_checks.*.measured_value' => ['required', 'numeric', 'decimal:0,4', 'between:-99999999.9999,99999999.9999'],
        ]);

        DB::transaction(function () use ($data, $test) {
            $test->load(['instrument.capacity.groups', 'items', 'dimensionChecks']);
            $selectedStatus = $data['status'] ?? null;
            $isManual = in_array($selectedStatus, ['SPARE', 'NA', 'SERVICE'], true);
            $submittedItems = collect($data['items'] ?? [])->keyBy('id');
            $submittedDimensions = collect($data['dimension_checks'] ?? [])->keyBy('id');
            $computedStatus = null;
            $avgCorrection = null;
            $selectedGroupId = $data['selected_group_id'] ?? $test->selected_group_id;
            $selectedGroup = $selectedGroupId ? $test->instrument?->capacity?->groups?->firstWhere('id', (int) $selectedGroupId) : null;
            if ($selectedGroupId !== null && ! $selectedGroup) {
                throw ValidationException::withMessages(['selected_group_id' => 'Grup penentu rata-rata tidak valid untuk alat ini.']);
            }

            if (! $isManual && $test->items->isEmpty() && ! in_array($selectedStatus, ['OK', 'NG'], true)) {
                throw ValidationException::withMessages(['status' => 'Pilih status OK atau NG untuk pengujian tanpa titik kapasitas.']);
            }

            if (! $isManual && $test->items->isNotEmpty()) {
                if ($submittedItems->keys()->sort()->values()->all() !== $test->items->pluck('id')->sort()->values()->all()) {
                    throw ValidationException::withMessages(['items' => 'Data titik uji tidak lengkap atau tidak valid.']);
                }
                $corrections = [];
                foreach ($test->items as $item) {
                    $reading = $submittedItems[$item->id]['reading_value'];
                    $correction = round((float) $reading - (float) $item->standard_value, 4);
                    if (! is_finite($correction) || abs($correction) > 99999999.9999) {
                        throw ValidationException::withMessages(['items' => 'Koreksi melampaui rentang penyimpanan.']);
                    }
                    $item->update([
                        'reading_value' => $reading,
                        'correction' => $correction,
                        'is_within_limit' => $test->min_correction_snapshot !== null && $test->max_correction_snapshot !== null
                            ? $correction >= (float) $test->min_correction_snapshot && $correction <= (float) $test->max_correction_snapshot
                            : null,
                    ]);
                    $corrections[] = $correction;
                }
                $selectedGroupOrder = $selectedGroup ? $test->instrument->capacity->groups->search(fn ($group) => $group->id === $selectedGroup->id) : null;
                $scoringItems = $selectedGroupOrder !== null
                    ? $test->items->filter(fn ($item) => $item->group_order === $selectedGroupOrder)
                    : $test->items;
                if ($scoringItems->isNotEmpty()) {
                    $avgCorrection = round($scoringItems->sum('correction') / $scoringItems->count(), 4);
                    if ($test->min_correction_snapshot !== null && $test->max_correction_snapshot !== null) {
                        $computedStatus = $avgCorrection >= (float) $test->min_correction_snapshot && $avgCorrection <= (float) $test->max_correction_snapshot ? 'OK' : 'NG';
                    }
                }
            }

            if (! $isManual && $test->dimensionChecks->isNotEmpty()) {
                if ($submittedDimensions->keys()->sort()->values()->all() !== $test->dimensionChecks->pluck('id')->sort()->values()->all()) {
                    throw ValidationException::withMessages(['dimension_checks' => 'Data pemeriksaan ukuran tidak lengkap atau tidak valid.']);
                }
                foreach ($test->dimensionChecks as $dimension) {
                    $measured = $submittedDimensions[$dimension->id]['measured_value'];
                    $dimension->update([
                        'measured_value' => $measured,
                        'is_within_range' => (float) $measured >= (float) $dimension->min_value && (float) $measured <= (float) $dimension->max_value,
                    ]);
                }
            }

            $testDate = Carbon::parse($data['test_date']);
            $test->update([
                'test_date' => $testDate->toDateString(),
                'next_test_date' => $testDate->copy()->addMonth()->toDateString(),
                'status' => $selectedStatus ?? $computedStatus ?? ($isManual ? $selectedStatus : $test->status),
                'selected_status' => $selectedStatus,
                'computed_status' => $computedStatus,
                'avg_correction' => $avgCorrection,
                'selected_group_id' => $selectedGroup?->id,
                'selected_group_name' => $selectedGroup?->name,
                'selected_group_reference_media' => $selectedGroup?->reference_media,
                'selected_group_order' => $selectedGroup ? $test->instrument->capacity->groups->search(fn ($group) => $group->id === $selectedGroup->id) : null,
                'reported_correction' => $data['reported_correction'] ?? null,
                'reported_acceptable_limit' => $data['reported_acceptable_limit'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return redirect()->route('tests.show', $test)->with('flash', ['success' => 'Pengujian berhasil diperbarui.']);
    }

    public function destroy(CalibrationTest $test)
    {
        DB::transaction(fn () => $test->delete());

        return redirect()->route('tests.index')->with('flash', ['success' => 'Pengujian berhasil dihapus.']);
    }

    public function show(CalibrationTest $test)
    {
        $test->load(['instrument', 'instrument.type', 'instrument.factory', 'instrument.department',
            'instrument.brand', 'instrument.capacity', 'instrument.acceptableLimit', 'instrument.specification', 'tester', 'items', 'dimensionChecks']);

        return Inertia::render('Tests/Show', ['test' => $test]);
    }
}
