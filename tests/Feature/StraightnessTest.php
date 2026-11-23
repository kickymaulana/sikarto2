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
use App\Models\Specification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StraightnessTest extends TestCase
{
    use RefreshDatabase;

    private User $inspector;

    private Instrument $instrument;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->inspector = User::create(['name' => 'Inspector', 'email' => 'straightness@test', 'password' => 'password']);
        $this->inspector->assignRole('inspector');
        $factory = Factory::create(['name' => 'Factory']);
        $department = Department::create(['name' => 'QA', 'factory_id' => $factory->id]);
        $type = InstrumentType::create(['name' => '  sTrAiGhTnEsS  ']);
        $brand = Brand::create(['name' => 'Brand']);
        $capacity = Capacity::create(['name' => 'Straightness', 'value' => 1, 'unit' => 'unit']);
        $limit = AcceptableLimit::create(['name' => 'Unused', 'min_correction' => -1, 'max_correction' => 1, 'unit' => 'mm']);
        $this->instrument = Instrument::create([
            'code' => 'ST.1', 'factory_id' => $factory->id, 'department_id' => $department->id,
            'instrument_type_id' => $type->id, 'brand_id' => $brand->id,
            'capacity_id' => $capacity->id, 'acceptable_limit_id' => $limit->id,
        ]);
        $this->actingAs($this->inspector);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'instrument_id' => $this->instrument->id,
            'test_date' => '2026-08-21',
            'selected_status' => 'NG',
            'straightness_checks' => [
                'dudukan' => "  rata\t baik  ",
                'tiang_kelurusan' => " tegak\n lurus ",
                'titik_tengah' => ' sesuai ',
            ],
        ], $overrides);
    }

    public function test_store_normalizes_free_text_and_saves_manual_status_without_numeric_checks(): void
    {
        foreach (['OK', 'NG'] as $status) {
            $this->post('/tests', $this->payload([
                'selected_status' => $status,
                'items' => 'ignored', 'dimension_checks' => 'ignored',
                'straightness_checks' => ['dudukan' => 'rusak', 'tiang_kelurusan' => " tegak\n lurus ", 'titik_tengah' => ' sesuai '],
            ]))->assertSessionHasNoErrors()->assertRedirect();
            $test = CalibrationTest::latest('id')->firstOrFail();
            $this->assertSame(['dudukan' => 'RUSAK', 'tiang_kelurusan' => 'TEGAK LURUS', 'titik_tengah' => 'SESUAI'], $test->straightness_checks);
            $this->assertSame($status, $test->status);
            $this->assertSame($status, $test->selected_status);
            $this->assertNull($test->computed_status);
            $this->assertNull($test->avg_correction);
            $this->assertSame('2026-09-21', $test->next_test_date->toDateString());
            $this->assertCount(0, $test->items);
            $this->assertCount(0, $test->dimensionChecks);
        }
    }

    public function test_rejects_missing_status_bypass_and_invalid_text_without_saving(): void
    {
        foreach ([null, 'SPARE', 'NA', 'SERVICE'] as $status) {
            $this->post('/tests', $this->payload(['selected_status' => $status]))->assertSessionHasErrors('selected_status');
        }
        foreach ([null, [], ['dudukan' => ' ', 'tiang_kelurusan' => 'x', 'titik_tengah' => 'x'], ['dudukan' => 123, 'tiang_kelurusan' => 'x', 'titik_tengah' => 'x'], ['dudukan' => str_repeat('a', 256), 'tiang_kelurusan' => 'x', 'titik_tengah' => 'x'], ['dudukan' => 'x', 'tiang_kelurusan' => 'x', 'titik_tengah' => 'x', 'extra' => 'x']] as $checks) {
            $this->post('/tests', $this->payload(['straightness_checks' => $checks]))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('calibration_tests', 0);
    }

    public function test_snapshot_remains_authoritative_after_type_rename_and_update_is_atomic(): void
    {
        $this->post('/tests', $this->payload())->assertSessionHasNoErrors();
        $test = CalibrationTest::firstOrFail();
        $this->instrument->type->update(['name' => 'Renamed']);
        $this->get('/tests/'.$test->id)->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tests/Show')->where('test.straightness_checks.dudukan', 'RATA BAIK'));
        $this->get('/tests/'.$test->id.'/edit')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tests/Edit')->where('test.straightness_checks.dudukan', 'RATA BAIK')
            ->where('straightnessSuggestions.dudukan', ['RATA BAIK']));
        $this->put('/tests/'.$test->id, $this->payload(['selected_status' => 'SERVICE']))->assertSessionHasErrors('selected_status');
        $this->assertSame('NG', $test->fresh()->status);
        $this->put('/tests/'.$test->id, $this->payload([
            'test_date' => '2026-09-21', 'selected_status' => 'OK',
            'straightness_checks' => ['dudukan' => ' baru ', 'tiang_kelurusan' => 'lurus', 'titik_tengah' => 'tengah'],
            'items' => 'ignored', 'dimension_checks' => 'ignored',
        ]))->assertSessionHasNoErrors();
        $test->refresh();
        $this->assertSame('BARU', $test->straightness_checks['dudukan']);
        $this->assertSame('OK', $test->status);
        $this->assertNull($test->computed_status);
        $this->assertNull($test->avg_correction);
        $this->assertSame('2026-10-21', $test->next_test_date->toDateString());
    }

    public function test_suggestions_are_normalized_distinct_per_field_and_capped_at_twenty(): void
    {
        for ($i = 0; $i < 25; $i++) {
            CalibrationTest::create([
                'instrument_id' => $this->instrument->id, 'tester_id' => $this->inspector->id,
                'test_date' => '2026-08-21', 'next_test_date' => '2026-09-21', 'status' => 'OK',
                'straightness_checks' => ['dudukan' => ' value '.$i.' ', 'tiang_kelurusan' => ' same  text ', 'titik_tengah' => ' same text '],
            ]);
        }
        $this->get('/tests/create')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tests/Create')->has('straightnessSuggestions.dudukan', 20)
            ->where('straightnessSuggestions.dudukan.0', 'VALUE 24')
            ->where('straightnessSuggestions.tiang_kelurusan', ['SAME TEXT'])
            ->where('straightnessSuggestions.titik_tengah', ['SAME TEXT']));
        $this->getJson('/tests/instruments/search?search=ST')->assertOk()
            ->assertJsonPath('data.0.type.name', '  sTrAiGhTnEsS  ');
        $this->getJson('/tests/instruments/'.$this->instrument->id)->assertOk()
            ->assertJsonPath('data.type.name', '  sTrAiGhTnEsS  ');
    }

    public function test_numeric_dimension_and_bypass_types_keep_existing_behavior(): void
    {
        $this->instrument->type->update(['name' => 'Timbangan']);
        $capacity = Capacity::create(['name' => '1 kg', 'value' => 1, 'unit' => 'kg']);
        $group = $capacity->groups()->create(['name' => 'Group', 'reference_media' => 'Weight', 'sort_order' => 0]);
        $standard = $group->standards()->create(['standard_value' => 100, 'sort_order' => 0]);
        $limit = AcceptableLimit::create(['name' => 'Limit', 'min_correction' => -5, 'max_correction' => 5, 'unit' => 'g']);
        $this->instrument->update(['capacity_id' => $capacity->id, 'acceptable_limit_id' => $limit->id]);
        $this->post('/tests', $this->payload(['items' => [['standard_template_id' => $standard->id, 'reading_value' => 102]]]))->assertSessionHasNoErrors();
        $test = CalibrationTest::latest('id')->firstOrFail();
        $this->assertSame('OK', $test->status);
        $this->assertEquals(2, $test->avg_correction);
        $this->assertNull($test->straightness_checks);
        $this->put('/tests/'.$test->id, ['test_date' => '2026-08-21', 'items' => [['id' => $test->items->first()->id, 'reading_value' => 110]]])->assertSessionHasNoErrors();
        $this->assertSame('NG', $test->fresh()->status);
        $specification = Specification::create(['name' => 'Dimension', 'length_min' => 1, 'length_max' => 5, 'dimension_unit' => 'mm']);
        $emptyCapacity = Capacity::create(['name' => 'Dimension only', 'value' => 1, 'unit' => 'mm']);
        $this->instrument->update(['capacity_id' => $emptyCapacity->id, 'specification_id' => $specification->id]);
        $this->post('/tests', $this->payload(['status' => 'NG', 'dimension_checks' => [['dimension' => 'length', 'measured_value' => 3]]]))->assertSessionHasNoErrors();
        $test = CalibrationTest::latest('id')->firstOrFail();
        $this->assertSame('NG', $test->status);
        $this->assertCount(1, $test->dimensionChecks);
        $this->assertNull($test->straightness_checks);
        foreach (['SPARE', 'NA', 'SERVICE'] as $status) {
            $this->post('/tests', $this->payload(['status' => $status, 'items' => 'ignored', 'dimension_checks' => 'ignored']))->assertSessionHasNoErrors();
            $test = CalibrationTest::latest('id')->firstOrFail();
            $this->assertSame($status, $test->status);
            $this->assertNull($test->straightness_checks);
            $this->assertSame('2026-09-21', $test->next_test_date->toDateString());
        }
    }

    public function test_permission_middleware_still_protects_writes(): void
    {
        $this->inspector->syncRoles(['admin']);
        $this->post('/tests', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('calibration_tests', 0);
    }

    public function test_migration_is_nullable_and_reversible_without_changing_existing_records(): void
    {
        $test = CalibrationTest::create([
            'instrument_id' => $this->instrument->id, 'tester_id' => $this->inspector->id,
            'test_date' => '2026-08-21', 'next_test_date' => '2026-09-21', 'status' => 'OK',
        ]);
        $this->assertNull($test->fresh()->straightness_checks);
        $migration = require database_path('migrations/2026_11_23_000001_add_straightness_checks_to_calibration_tests_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('calibration_tests', 'straightness_checks'));
        $this->assertDatabaseHas('calibration_tests', ['id' => $test->id, 'status' => 'OK']);
        $migration->up();
        $this->assertTrue(Schema::hasColumn('calibration_tests', 'straightness_checks'));
        $this->assertNull($test->fresh()->straightness_checks);
    }
}
