<?php

namespace Tests\Feature;

use App\Exports\InternalKalibrasiExport;
use App\Models\AcceptableLimit;
use App\Models\Brand;
use App\Models\CalibrationTest;
use App\Models\Capacity;
use App\Models\Department;
use App\Models\Factory;
use App\Models\Instrument;
use App\Models\InstrumentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InternalKalibrasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(): User
    {
        $user = User::create([
            'name' => 'Inspector',
            'email' => 'internal-report-'.uniqid().'@test',
            'password' => 'password',
            'is_approved' => true,
        ]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function makeTest(string $date, float $min = -0.5, float $max = 0.5): CalibrationTest
    {
        $factory = Factory::create(['name' => 'Plant '.uniqid()]);
        $department = Department::create(['name' => 'QA', 'factory_id' => $factory->id]);
        $type = InstrumentType::create(['name' => 'Caliper '.uniqid()]);
        $brand = Brand::create(['name' => 'Brand '.uniqid()]);
        $capacity = Capacity::create(['name' => '100 MM', 'value' => 100, 'unit' => 'mm']);
        $limit = AcceptableLimit::create([
            'name' => 'Limit',
            'min_correction' => $min,
            'max_correction' => $max,
            'unit' => 'mm',
        ]);
        $instrument = Instrument::create([
            'code' => 'CAL-'.uniqid(),
            'factory_id' => $factory->id,
            'department_id' => $department->id,
            'instrument_type_id' => $type->id,
            'brand_id' => $brand->id,
            'capacity_id' => $capacity->id,
            'acceptable_limit_id' => $limit->id,
        ]);

        return CalibrationTest::create([
            'instrument_id' => $instrument->id,
            'test_date' => $date,
            'next_test_date' => date('Y-m-d', strtotime($date.' +1 month')),
            'tester_id' => $this->user()->id,
            'status' => 'OK',
            'avg_correction' => 0,
            'min_correction_snapshot' => $min,
            'max_correction_snapshot' => $max,
        ]);
    }

    public function test_backfill_reported_values_fills_only_empty_fields(): void
    {
        $test = $this->makeTest('2026-05-10');
        $test->update(['reported_acceptable_limit' => 'CUSTOM LIMIT']);

        Artisan::call('calibration:backfill-reported-values');
        $test->refresh();

        $this->assertSame('OK', $test->reported_correction);
        $this->assertSame('CUSTOM LIMIT', $test->reported_acceptable_limit);
    }

    public function test_internal_report_filters_by_calibration_month(): void
    {
        $may = $this->makeTest('2026-05-10');
        $june = $this->makeTest('2026-06-10');

        $this->actingAs($this->user())->get('/laporan/internal-kalibrasi?year=2026&month=5')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Laporan/InternalKalibrasi')
                ->where('year', 2026)
                ->where('month', 5)
                ->has('tests', 1)
                ->where('tests.0.id', $may->id)
                ->where('tests.0.avg_correction', '')
                ->where('tests.0.acceptable_limit', '')
                ->missing('tests.1'));

        $this->assertNotSame($may->id, $june->id);
    }

    public function test_internal_report_formats_correction_and_excel(): void
    {
        $zero = $this->makeTest('2026-05-10');
        $zero->update(['reported_correction' => 'OK']);
        $positive = $this->makeTest('2026-05-11');
        $positive->update(['reported_correction' => '+2 GR']);
        $negative = $this->makeTest('2026-05-12');
        $negative->update(['reported_correction' => '-2 GR']);
        $decimal = $this->makeTest('2026-05-13');
        $decimal->update(['reported_correction' => '+0,1 GR']);
        $tiny = $this->makeTest('2026-05-14');
        $tiny->update(['reported_correction' => 'OK']);

        $response = $this->actingAs($this->user())->get('/laporan/internal-kalibrasi?year=2026&month=5');
        $rows = $response->original?->getData()['page']['props']['tests'] ?? [];
        $values = collect($rows)->mapWithKeys(fn ($row) => [$row['id'] => $row['avg_correction']])->all();

        $this->assertSame('OK', $values[$zero->id]);
        $this->assertSame('+2 GR', $values[$positive->id]);
        $this->assertSame('-2 GR', $values[$negative->id]);
        $this->assertSame('+0,1 GR', $values[$decimal->id]);
        $this->assertSame('OK', $values[$tiny->id]);

        $export = new InternalKalibrasiExport(collect($rows)->map(fn ($row) => (array) $row)->all());
        $exportValues = collect($export->array())->mapWithKeys(fn ($row) => [$row[0] => $row[5]]);
        $this->assertSame('OK', $exportValues[$zero->instrument->code]);
        $this->assertSame('+2 GR', $exportValues[$positive->instrument->code]);
        $this->assertSame('-2 GR', $exportValues[$negative->instrument->code]);
        $this->assertSame('+0,1 GR', $exportValues[$decimal->instrument->code]);
        $this->assertSame('OK', $exportValues[$tiny->instrument->code]);
    }

    public function test_internal_report_formats_symmetric_acceptable_limit(): void
    {
        $test = $this->makeTest('2026-05-10');
        $test->update(['reported_acceptable_limit' => '± 0,5 MM']);

        $this->actingAs($this->user())->get('/laporan/internal-kalibrasi?year=2026&month=5')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tests.0.id', $test->id)
                ->where('tests.0.acceptable_limit', '± 0,5 MM'));
    }
}
