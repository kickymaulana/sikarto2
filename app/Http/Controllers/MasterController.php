<?php

namespace App\Http\Controllers;

use App\Exports\MatrixExport;
use App\Models\AcceptableLimit;
use App\Models\Brand;
use App\Models\CalibrationTest;
use App\Models\Capacity;
use App\Models\Department;
use App\Models\Factory;
use App\Models\Instrument;
use App\Models\InstrumentType;
use App\Models\Specification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class MasterController extends Controller
{
    private array $entities = [
        'factories' => [
            'model' => Factory::class,
            'label' => 'Factory',
            'columns' => [['key' => 'name', 'label' => 'Nama']],
            'fields' => [['key' => 'name', 'label' => 'Nama', 'type' => 'text']],
            'rules' => ['name' => 'required|string|max:255'],
        ],
        'departments' => [
            'model' => Department::class,
            'label' => 'Departemen',
            'columns' => [['key' => 'name', 'label' => 'Nama'], ['key' => 'factory', 'label' => 'Factory']],
            'fields' => [
                ['key' => 'factory_id', 'label' => 'Factory', 'type' => 'select', 'options' => 'factories'],
                ['key' => 'name', 'label' => 'Nama', 'type' => 'text'],
            ],
            'rules' => [
                'factory_id' => 'required|exists:factories,id',
                'name' => 'required|string|max:255',
            ],
        ],
        'types' => [
            'model' => InstrumentType::class,
            'label' => 'Jenis Alat',
            'columns' => [['key' => 'name', 'label' => 'Nama']],
            'fields' => [['key' => 'name', 'label' => 'Nama', 'type' => 'text']],
            'rules' => ['name' => 'required|string|max:255'],
        ],
        'brands' => [
            'model' => Brand::class,
            'label' => 'Merk',
            'columns' => [['key' => 'name', 'label' => 'Nama']],
            'fields' => [['key' => 'name', 'label' => 'Nama', 'type' => 'text']],
            'rules' => ['name' => 'required|string|max:255'],
        ],
        'capacities' => [
            'model' => Capacity::class,
            'label' => 'Kapasitas',
            'columns' => [['key' => 'name', 'label' => 'Nama'], ['key' => 'value', 'label' => 'Nilai'], ['key' => 'unit', 'label' => 'Satuan']],
            'fields' => [
                ['key' => 'name', 'label' => 'Nama', 'type' => 'text'],
                ['key' => 'value', 'label' => 'Nilai', 'type' => 'number', 'step' => '0.0001'],
                ['key' => 'unit', 'label' => 'Satuan', 'type' => 'text'],
            ],
            'rules' => [
                'name' => 'required|string|max:255',
                'value' => 'required|numeric',
                'unit' => 'required|string|max:20',
            ],
        ],
        'limits' => [
            'model' => AcceptableLimit::class,
            'label' => 'Acceptable Limit',
            'columns' => [
                ['key' => 'name', 'label' => 'Nama'],
                ['key' => 'min_correction', 'label' => 'Min'],
                ['key' => 'max_correction', 'label' => 'Max'],
                ['key' => 'unit', 'label' => 'Satuan'],
            ],
            'fields' => [
                ['key' => 'name', 'label' => 'Nama (contoh: ±5 gr)', 'type' => 'text'],
                ['key' => 'min_correction', 'label' => 'Koreksi Min', 'type' => 'number', 'step' => '0.0001'],
                ['key' => 'max_correction', 'label' => 'Koreksi Max', 'type' => 'number', 'step' => '0.0001'],
                ['key' => 'unit', 'label' => 'Satuan', 'type' => 'text'],
            ],
            'rules' => [
                'name' => 'required|string|max:255',
                'min_correction' => 'required|numeric',
                'max_correction' => 'required|numeric|gte:min_correction',
                'unit' => 'required|string|max:20',
            ],
        ],
        'specifications' => [
            'model' => Specification::class,
            'label' => 'Spesifikasi',
            'columns' => [['key' => 'name', 'label' => 'Nama']],
            'fields' => [
                ['key' => 'name', 'label' => 'Nama', 'type' => 'text'],
                ['key' => 'length_min', 'label' => 'Panjang Min', 'type' => 'number', 'step' => '0.0001'],
                ['key' => 'length_max', 'label' => 'Panjang Max', 'type' => 'number', 'step' => '0.0001'],
                ['key' => 'width_min', 'label' => 'Lebar Min', 'type' => 'number', 'step' => '0.0001'],
                ['key' => 'width_max', 'label' => 'Lebar Max', 'type' => 'number', 'step' => '0.0001'],
                ['key' => 'diameter_min', 'label' => 'Diameter Min', 'type' => 'number', 'step' => '0.0001'],
                ['key' => 'diameter_max', 'label' => 'Diameter Max', 'type' => 'number', 'step' => '0.0001'],
                ['key' => 'dimension_unit', 'label' => 'Satuan Ukuran', 'type' => 'text'],
            ],
            'rules' => [
                'name' => 'required|string|max:255',
                'length_min' => 'nullable|numeric|decimal:0,4|between:-99999999.9999,99999999.9999',
                'length_max' => 'nullable|numeric|decimal:0,4|between:-99999999.9999,99999999.9999',
                'width_min' => 'nullable|numeric|decimal:0,4|between:-99999999.9999,99999999.9999',
                'width_max' => 'nullable|numeric|decimal:0,4|between:-99999999.9999,99999999.9999',
                'diameter_min' => 'nullable|numeric|decimal:0,4|between:-99999999.9999,99999999.9999',
                'diameter_max' => 'nullable|numeric|decimal:0,4|between:-99999999.9999,99999999.9999',
                'dimension_unit' => 'nullable|string|max:20',
            ],
        ],
    ];

    public function matrix(Request $request)
    {
        [$year, $typeId, $factoryId, $types, $factories, $rows] = $this->buildMatrixData($request, null);

        return Inertia::render('Laporan/Matrix', [
            'year' => $year,
            'typeId' => $typeId,
            'factoryId' => $factoryId,
            'types' => $types,
            'factories' => $factories,
            'rows' => $rows,
        ]);
    }

    public function matrixExport(Request $request)
    {
        [$year, $typeId, $factoryId, $types, $factories, $rows] = $this->buildMatrixData($request, null);

        $typeName = $typeId ? InstrumentType::find($typeId)?->name : 'Semua Jenis';
        $fileName = 'Matriks_Uji_'.str_replace(' ', '-', (string) $typeName).'-'.$year.'.xlsx';

        return Excel::download(new MatrixExport($rows, $year, (string) $typeName), $fileName);
    }

    private function buildMatrixData(Request $request, ?int $forcedYear): array
    {
        $year = $forcedYear ?? ($request->filled('year') ? (int) $request->year : now()->year);
        $months = range(1, 12);

        $defaultType = InstrumentType::where('name', 'Timbangan Digital')->first();
        $typeId = $request->filled('type_id') ? (int) $request->type_id : ($defaultType?->id);
        $factoryId = $request->filled('factory_id') ? (int) $request->factory_id : null;

        $tests = CalibrationTest::with('instrument')
            ->where(function ($query) use ($year) {
                $query->whereYear('test_date', $year)
                    ->orWhereYear('next_test_date', $year);
            })
            ->orderBy('test_date')
            ->get(['id', 'instrument_id', 'test_date', 'next_test_date', 'status']);

        $instrumentsQuery = Instrument::with(['type', 'brand', 'capacity', 'factory', 'department']);
        if ($typeId) {
            $instrumentsQuery->where('instrument_type_id', $typeId);
        }
        if ($factoryId) {
            $instrumentsQuery->where('factory_id', $factoryId);
        }
        $instruments = $instrumentsQuery->get();

        $types = InstrumentType::orderBy('name')->get(['id', 'name']);
        $factories = Factory::orderBy('name')->get(['id', 'name']);

        $rows = [];
        foreach ($instruments as $instrument) {
            $testCell = [];
            $nextCell = [];
            foreach ($months as $m) {
                $testCell[$m] = ['day' => '', 'status' => 'none'];
                $nextCell[$m] = ['day' => '', 'status' => 'none'];
            }
            foreach ($tests->where('instrument_id', $instrument->id) as $test) {
                if ($test->test_date->year === $year) {
                    $testMonth = (int) $test->test_date->format('n');
                    $testCell[$testMonth] = [
                        'day' => (string) (int) $test->test_date->format('j'),
                        'status' => $test->status,
                    ];
                }

                if ($test->next_test_date?->year === $year) {
                    $nextMonth = (int) $test->next_test_date->format('n');
                    $nextCell[$nextMonth] = [
                        'day' => (string) (int) $test->next_test_date->format('j'),
                        'status' => $test->status,
                    ];
                }
            }
            $location = $instrument->department?->name;
            $rows[] = [
                'code' => $instrument->code,
                'type' => $instrument->type?->name,
                'brand' => $instrument->brand?->name,
                'capacity' => $instrument->capacity?->name,
                'location' => $location !== '' ? $location : null,
                'test_cell' => $testCell,
                'next_cell' => $nextCell,
            ];
        }

        usort($rows, fn ($a, $b) => $this->naturalCodeCompare($a['code'], $b['code']));

        return [$year, $typeId, $factoryId, $types, $factories, $rows];
    }

    private function naturalCodeCompare(string $a, string $b): int
    {
        $aParts = preg_split('/(\d+)/', $a, -1, PREG_SPLIT_DELIM_CAPTURE);
        $bParts = preg_split('/(\d+)/', $b, -1, PREG_SPLIT_DELIM_CAPTURE);

        $count = max(count($aParts), count($bParts));
        for ($i = 0; $i < $count; $i++) {
            $aSeg = $aParts[$i] ?? '';
            $bSeg = $bParts[$i] ?? '';
            $aIsNum = $aSeg !== '' && ctype_digit($aSeg);
            $bIsNum = $bSeg !== '' && ctype_digit($bSeg);

            $cmp = 0;
            if ($aIsNum && $bIsNum) {
                $cmp = (int) $aSeg <=> (int) $bSeg;
            } elseif ($aIsNum !== $bIsNum) {
                $cmp = $aIsNum ? -1 : 1;
            } else {
                $cmp = strcmp($aSeg, $bSeg);
            }
            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return 0;
    }

    public function index(Request $request, string $entity)
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'in:20,50,100'],
        ]);

        abort_unless(isset($this->entities[$entity]), 404);

        $config = $this->entities[$entity];
        $query = $config['model']::query();

        if ($entity === 'departments') {
            $query->with('factory');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($config, $search) {
                foreach ($config['columns'] as $column) {
                    if ($column['key'] === 'factory') {
                        $q->orWhereHas('factory', fn ($f) => $f->where('name', 'like', "%{$search}%"));
                    } else {
                        $q->orWhere($column['key'], 'like', "%{$search}%");
                    }
                }
            });
        }

        $items = $query->orderBy('id', 'desc')->paginate($request->integer('per_page', 20))->withQueryString();

        return Inertia::render('Masters/Index', [
            'entity' => $entity,
            'config' => $config,
            'items' => $items,
            'options' => $this->options($entity),
        ]);
    }

    public function create(string $entity)
    {
        abort_unless(isset($this->entities[$entity]), 404);

        $config = $this->entities[$entity];

        return Inertia::render('Masters/Form', [
            'entity' => $entity,
            'config' => $config,
            'item' => null,
            'options' => $this->options($entity),
        ]);
    }

    public function edit(string $entity, int $id)
    {
        abort_unless(isset($this->entities[$entity]), 404);

        $config = $this->entities[$entity];
        $item = $config['model']::query();

        if ($entity === 'capacities') {
            $item->with('groups.standards');
        }

        $item = $item->findOrFail($id);

        return Inertia::render('Masters/Form', [
            'entity' => $entity,
            'config' => $config,
            'item' => $item,
            'options' => $this->options($entity),
        ]);
    }

    public function store(Request $request, string $entity)
    {
        abort_unless(isset($this->entities[$entity]), 404);

        $config = $this->entities[$entity];
        $data = $request->validate($config['rules'] + ($entity === 'capacities' ? $this->groupRules() : []));
        if ($entity === 'specifications') {
            $this->validateDimensionRanges($data);
        }

        DB::transaction(function () use ($config, $data, $entity) {
            $record = $config['model']::create(collect($data)->except('groups')->all());
            if ($entity === 'capacities') {
                $this->syncGroups($record, $data['groups']);
            }
        });

        return redirect()->route('masters.index', $entity)
            ->with('flash', ['success' => "{$config['label']} berhasil ditambahkan."]);
    }

    public function update(Request $request, string $entity, int $id)
    {
        abort_unless(isset($this->entities[$entity]), 404);

        $config = $this->entities[$entity];
        $data = $request->validate($config['rules'] + ($entity === 'capacities' ? $this->groupRules() : []));
        if ($entity === 'specifications') {
            $this->validateDimensionRanges($data);
        }

        DB::transaction(function () use ($config, $data, $entity, $id) {
            $record = $config['model']::lockForUpdate()->findOrFail($id);
            $record->update(collect($data)->except('groups')->all());
            if ($entity === 'capacities') {
                $this->syncGroups($record, $data['groups']);
            }
        });

        return redirect()->route('masters.index', $entity)
            ->with('flash', ['success' => "{$config['label']} berhasil diperbarui."]);
    }

    public function destroy(string $entity, int $id)
    {
        abort_unless(isset($this->entities[$entity]), 404);

        $config = $this->entities[$entity];
        $config['model']::findOrFail($id)->delete();

        return redirect()->route('masters.index', $entity)
            ->with('flash', ['success' => "{$config['label']} berhasil dihapus."]);
    }

    private function groupRules(): array
    {
        return [
            'groups' => 'present|array',
            'groups.*.id' => 'sometimes|required|integer|distinct',
            'groups.*.name' => 'required|string|max:255',
            'groups.*.reference_media' => 'required|string|max:255',
            'groups.*.standards' => 'required|array|min:1',
            'groups.*.standards.*.id' => 'sometimes|required|integer|distinct',
            'groups.*.standards.*.standard_value' => 'required|numeric|decimal:0,4|between:-99999999.9999,99999999.9999',
        ];
    }

    private function validateDimensionRanges(array $data): void
    {
        $hasRange = false;
        foreach (['length', 'width', 'diameter'] as $dimension) {
            $min = $data["{$dimension}_min"] ?? null;
            $max = $data["{$dimension}_max"] ?? null;
            if (($min === null) !== ($max === null)) {
                throw ValidationException::withMessages(["{$dimension}_min" => 'Batas minimum dan maksimum harus diisi bersama.']);
            }
            if ($min !== null && (float) $max < (float) $min) {
                throw ValidationException::withMessages(["{$dimension}_max" => 'Batas maksimum harus lebih besar atau sama dengan minimum.']);
            }
            $hasRange = $hasRange || $min !== null;
        }
        if ($hasRange && empty($data['dimension_unit'])) {
            throw ValidationException::withMessages(['dimension_unit' => 'Satuan ukuran wajib diisi bila ada rentang ukuran.']);
        }
    }

    private function syncGroups(Capacity $capacity, array $groups): void
    {
        $keptGroups = [];
        foreach (array_values($groups) as $groupOrder => $data) {
            $group = isset($data['id']) ? $capacity->groups()->find($data['id']) : $capacity->groups()->make();
            if (! $group) {
                throw ValidationException::withMessages(["groups.$groupOrder.id" => 'Grup bukan milik kapasitas ini.']);
            }
            $group->fill([
                'name' => $data['name'],
                'reference_media' => $data['reference_media'],
                'sort_order' => $groupOrder,
            ])->save();
            $keptGroups[] = $group->id;
            $keptStandards = [];
            foreach (array_values($data['standards']) as $pointOrder => $point) {
                $standard = isset($point['id']) ? $group->standards()->find($point['id']) : $group->standards()->make();
                if (! $standard) {
                    throw ValidationException::withMessages(["groups.$groupOrder.standards.$pointOrder.id" => 'Titik uji bukan milik grup ini.']);
                }
                $standard->fill(['standard_value' => $point['standard_value'], 'sort_order' => $pointOrder])->save();
                $keptStandards[] = $standard->id;
            }
            $group->standards()->whereNotIn('id', $keptStandards)->delete();
        }
        $capacity->groups()->whereNotIn('id', $keptGroups)->delete();
    }

    private function options(string $entity): array
    {
        $options = [];
        foreach ($this->entities as $key => $config) {
            foreach ($config['fields'] as $field) {
                if (($field['type'] ?? '') === 'select') {
                    $options[$field['options']] = $this->entities[$field['options']]['model']::orderBy('name')->get();
                }
            }
        }
        if ($entity === 'departments') {
            $options['factories'] = Factory::orderBy('name')->get();
        }

        return $options;
    }
}
