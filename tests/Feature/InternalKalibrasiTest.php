<?php

namespace Tests\Feature;

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
                ->missing('tests.1'));

        $this->assertNotSame($may->id, $june->id);
    }

    public function test_internal_report_formats_symmetric_acceptable_limit(): void
    {
        $test = $this->makeTest('2026-05-10');

        $this->actingAs($this->user())->get('/laporan/internal-kalibrasi?year=2026&month=5')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tests.0.id', $test->id)
                ->where('tests.0.acceptable_limit', '± 0,5 MM'));
    }
}
