<?php

namespace Tests\Feature;

use App\Exports\InternalKalibrasiExport;
use App\Models\AcceptableLimit;
use App\Models\Brand;
use App\Models\CalibrationTest;
use App\Models\CalibrationTestDimensionCheck;
use App\Models\Capacity;
use App\Models\Department;
use App\Models\Factory;
use App\Models\Instrument;
use App\Models\InstrumentType;
use App\Models\Specification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StraightnessReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::create([
            'name' => 'Kalibrator',
            'email' => 'straightness-report@test.local',
            'password' => 'password',
            'is_approved' => true,
        ]);
        $this->user->assignRole('super_admin');
    }

    private function makeTest(InstrumentType $type, string $status = 'OK', ?array $checks = null): CalibrationTest
    {
        $factory = Factory::create(['name' => 'Plant '.$type->id.$status]);
        $department = Department::create(['name' => 'QA '.$type->id.$status, 'factory_id' => $factory->id]);
        $brand = Brand::create(['name' => 'Brand '.$type->id.$status]);
        $capacity = Capacity::create(['name' => '100 MM', 'value' => 100, 'unit' => 'mm']);
        $limit = AcceptableLimit::create([
            'name' => 'Limit '.$type->id.$status,
            'min_correction' => -0.5,
            'max_correction' => 0.5,
            'unit' => 'mm',
        ]);
        $instrument = Instrument::create([
            'code' => 'STR-'.$type->id.$status,
            'factory_id' => $factory->id,
            'department_id' => $department->id,
            'instrument_type_id' => $type->id,
            'brand_id' => $brand->id,
            'capacity_id' => $capacity->id,
            'acceptable_limit_id' => $limit->id,
        ]);

        return CalibrationTest::create([
            'instrument_id' => $instrument->id,
            'test_date' => '2026-10-14',
            'next_test_date' => '2026-11-14',
            'tester_id' => $this->user->id,
            'status' => $status,
            'straightness_checks' => $checks,
        ]);
    }

    private function rows(string $query): array
    {
        $response = $this->actingAs($this->user)->get('/laporan/internal-kalibrasi?year=2026&'.$query);

        return $response->original?->getData()['page']['props']['tests'] ?? [];
    }

    public function test_type_18_mode_lists_instruments_and_uses_latest_dimensions_and_specification(): void
    {
        while (InstrumentType::count() < 17) {
            InstrumentType::create(['name' => 'Type '.(InstrumentType::count() + 1)]);
        }
        $type = InstrumentType::create(['name' => 'Caliper']);
        $test = $this->makeTest($type);
        $specification = Specification::create(['name' => 'KINGFA D MINIMUM']);
        $test->instrument->update(['specification_id' => $specification->id]);
        foreach ([
            ['dimension' => 'length', 'label' => 'Panjang', 'measured_value' => 4.25, 'sort_order' => 1],
            ['dimension' => 'width', 'label' => 'Lebar', 'measured_value' => 2.5, 'sort_order' => 2],
            ['dimension' => 'diameter', 'label' => 'Diameter', 'measured_value' => 3, 'sort_order' => 3],
        ] as $check) {
            CalibrationTestDimensionCheck::create([
                'calibration_test_id' => $test->id,
                'min_value' => 0,
                'max_value' => 10,
                'unit' => 'mm',
                'is_within_range' => true,
                ...$check,
            ]);
        }
        $withoutTest = Instrument::create([
            'code' => 'PR.KD.9',
            'factory_id' => $test->instrument->factory_id,
            'department_id' => $test->instrument->department_id,
            'instrument_type_id' => $type->id,
            'brand_id' => $test->instrument->brand_id,
            'capacity_id' => $test->instrument->capacity_id,
            'acceptable_limit_id' => $test->instrument->acceptable_limit_id,
            'specification_id' => $specification->id,
        ]);

        $response = $this->actingAs($this->user)->get('/laporan/internal-kalibrasi?year=2026&type_id=18');
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('mode', 'type_18')
            ->has('tests', 2)
            ->where('tests.0.code', 'PR.KD.9')
            ->where('tests.0.length', null)
            ->where('tests.0.test_date', null)
            ->where('tests.1.code', $test->instrument->code)
            ->where('tests.1.length', 4.25)
            ->where('tests.1.width', 2.5)
            ->where('tests.1.diameter', 3)
            ->where('tests.1.specification', 'KINGFA D MINIMUM')
            ->where('tests.1.test_date', '14 Oktober 2026')
            ->where('tests.1.status', 'OK')
            ->where('tests.1.next_test_date', '14 November 2026')
            ->where('tests.1.tester', 'Kalibrator'));

        $this->assertDatabaseHas('instruments', ['id' => $withoutTest->id]);
    }

    public function test_type_18_export_has_expected_columns_and_dash_for_missing_values(): void
    {
        $export = new InternalKalibrasiExport([[
            'code' => 'PR.KD.8',
            'location' => 'QA. PRESHIPMENT',
            'specification' => 'KINGFA D MINIMUM',
        ]], 'type_18');

        $this->assertSame([
            'NO', 'KODE', 'LOKASI', "PANJANG TAPAK\n(mm)", "LEBAR TAPAK\n(mm)", "DIAMETER\n(mm)",
            'SPESIFIKASI', 'TANGGAL KALIBRASI', 'FREK. KALIBRASI', 'HASIL KALIBRASI', 'DUE DATE', 'KALIBRATOR',
        ], $export->headings());
        $this->assertSame([
            51, 'PR.KD.8', 'QA. PRESHIPMENT', '—', '—', '—', 'KINGFA D MINIMUM', '—', 'PERBULAN', '—', '—', '—',
        ], $export->array()[0]);
    }

    public function test_special_mode_requires_selected_trimmed_uppercase_straightness_type(): void
    {
        $straightness = InstrumentType::create(['name' => '  straightness  ']);
        $other = InstrumentType::create(['name' => 'Caliper']);
        $this->makeTest($straightness, checks: ['dudukan' => 'BAIK', 'tiang_kelurusan' => 'LURUS', 'titik_tengah' => 'TEPAT']);
        $this->makeTest($other);

        $this->actingAs($this->user)->get('/laporan/internal-kalibrasi?year=2026&type_id='.$straightness->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('mode', 'straightness')
                ->has('tests', 1)
                ->where('tests.0.dudukan', 'BAIK')
                ->where('tests.0.tiang_kelurusan', 'LURUS')
                ->where('tests.0.titik_tengah', 'TEPAT')
                ->where('tests.0.test_date', '14 Oktober 2026')
                ->where('tests.0.next_test_date', '14 November 2026'));

        $this->actingAs($this->user)->get('/laporan/internal-kalibrasi?year=2026')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('mode', 'default')->has('tests', 2)->missing('tests.0.dudukan'));

        $this->actingAs($this->user)->get('/laporan/internal-kalibrasi?year=2026&type_id='.$other->id)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('mode', 'default')->has('tests', 1)->missing('tests.0.dudukan'));
    }

    public function test_special_report_keeps_missing_snapshot_empty_and_ng_due_date(): void
    {
        $type = InstrumentType::create(['name' => 'STRAIGHTNESS']);
        $test = $this->makeTest($type, 'NG');

        $this->actingAs($this->user)->get('/laporan/internal-kalibrasi?year=2026&type_id='.$type->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tests.0.id', $test->id)
                ->where('tests.0.dudukan', null)
                ->where('tests.0.tiang_kelurusan', null)
                ->where('tests.0.titik_tengah', null)
                ->where('tests.0.status', 'NG')
                ->where('tests.0.next_test_date', '14 November 2026'));
    }

    public function test_special_export_has_exact_headings_order_and_content(): void
    {
        $type = InstrumentType::create(['name' => 'STRAIGHTNESS']);
        $test = $this->makeTest($type, 'NG', ['dudukan' => 'BAIK', 'tiang_kelurusan' => 'LURUS', 'titik_tengah' => 'TEPAT']);
        $rows = $this->rows('type_id='.$type->id);
        $export = new InternalKalibrasiExport($rows, 'straightness');

        $this->assertSame([
            'NO.', 'KODE', 'LOKASI', 'DUDUKAN', 'TIANG KELURUSAN', 'TITIK TENGAH',
            'TGL KALIBRASI', 'FREK KALIBRASI', 'HASIL KALIBRASI', 'DUE DATE', 'KALIBRATOR',
        ], $export->headings());
        $this->assertSame([
            1, $test->instrument->code, $test->instrument->department->name, 'BAIK', 'LURUS', 'TEPAT',
            '14 Oktober 2026', 'PERBULAN', 'NOT ACCEPTED', '14 November 2026', 'Kalibrator',
        ], $export->array()[0]);
    }

    public function test_special_export_shows_dashes_for_missing_historic_snapshot(): void
    {
        $type = InstrumentType::create(['name' => 'STRAIGHTNESS']);
        $this->makeTest($type);
        $export = new InternalKalibrasiExport($this->rows('type_id='.$type->id), 'straightness');

        $this->assertSame(['—', '—', '—'], array_slice($export->array()[0], 3, 3));
    }
}
