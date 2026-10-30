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
use App\Models\StandardTemplate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CalibrationTestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeInstrument(): Instrument
    {
        $factory = Factory::create(['name' => 'KIM']);
        $dept = Department::create(['factory_id' => $factory->id, 'name' => 'Filling']);
        $type = InstrumentType::create(['name' => 'Timbangan']);
        $brand = Brand::create(['name' => 'DICSON']);
        $capacity = Capacity::create(['name' => '3 KG', 'value' => 3, 'unit' => 'kg']);
        $group = $capacity->groups()->create(['name' => 'Penimbangan', 'reference_media' => 'Anak Timbangan', 'sort_order' => 0]);
        $group->standards()->createMany([
            ['standard_value' => 500, 'sort_order' => 0],
            ['standard_value' => 700, 'sort_order' => 1],
        ]);
        $limit = AcceptableLimit::create(['name' => '±5 gr', 'min_correction' => -5, 'max_correction' => 5, 'unit' => 'gr']);

        return Instrument::create([
            'code' => 'W.FL.5',
            'factory_id' => $factory->id,
            'department_id' => $dept->id,
            'instrument_type_id' => $type->id,
            'brand_id' => $brand->id,
            'capacity_id' => $capacity->id,
            'acceptable_limit_id' => $limit->id,
        ]);
    }

    private function inspector(): User
    {
        $user = User::create(['name' => 'Inspector', 'email' => 'inspector@test', 'password' => 'password']);
        $user->assignRole('inspector');

        return $user;
    }

    public function test_pass_when_all_corrections_within_limit(): void
    {
        $instrument = $this->makeInstrument();

        $response = $this->actingAs($this->inspector())->post('/tests', [
            'instrument_id' => $instrument->id,
            'test_date' => '2026-08-01',
            'items' => [
                ['standard_template_id' => StandardTemplate::where('standard_value', 500)->value('id'), 'reading_value' => 502],
                ['standard_template_id' => StandardTemplate::where('standard_value', 700)->value('id'), 'reading_value' => 704],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $test = CalibrationTest::first();
        $this->assertNotNull($test);
        $this->assertEquals('OK', $test->status);
        $this->assertEquals('2026-09-01', $test->next_test_date->toDateString());
        $this->assertEquals(2, $test->items()->count());
        $this->assertTrue($test->items()->first()->is_within_limit);
        $this->assertNotNull($test->avg_correction);
    }

    public function test_selected_group_controls_average_while_all_points_are_saved(): void
    {
        $instrument = $this->makeInstrument();
        $secondGroup = $instrument->capacity->groups()->create(['name' => 'Second', 'reference_media' => 'Blok', 'sort_order' => 1]);
        $secondGroup->standards()->create(['standard_value' => 100, 'sort_order' => 0]);
        $templates = StandardTemplate::orderBy('id')->get();
        $response = $this->actingAs($this->inspector())->post('/tests', [
            'instrument_id' => $instrument->id,
            'test_date' => '2026-08-01',
            'selected_group_id' => $secondGroup->id,
            'items' => $templates->map(fn ($template) => [
                'standard_template_id' => $template->id,
                'reading_value' => $template->standard_value + ($template->standard_group_id === $secondGroup->id ? 4 : 100),
            ])->all(),
        ]);

        $response->assertSessionHasNoErrors();
        $test = CalibrationTest::latest('id')->firstOrFail();
        $this->assertSame('OK', $test->status);
        $this->assertEquals(4, $test->avg_correction);
        $this->assertCount(3, $test->items);
        $this->assertSame($secondGroup->id, $test->selected_group_id);
        $this->assertSame('Second', $test->selected_group_name);
        $this->assertSame('Blok', $test->selected_group_reference_media);
    }

    public function test_dimension_checks_are_snapshotted_without_changing_status(): void
    {
        $instrument = $this->makeInstrument();
        $specification = Specification::create([
            'name' => 'Tapak Holder',
            'length_min' => 50.7,
            'length_max' => 54,
            'width_min' => 25.7,
            'width_max' => 26,
            'diameter_min' => 88.5,
            'diameter_max' => 89,
            'dimension_unit' => 'mm',
        ]);
        $instrument->update(['specification_id' => $specification->id]);

        $response = $this->actingAs($this->inspector())->post('/tests', [
            'instrument_id' => $instrument->id,
            'test_date' => '2026-08-01',
            'items' => [
                ['standard_template_id' => StandardTemplate::where('standard_value', 500)->value('id'), 'reading_value' => 500],
                ['standard_template_id' => StandardTemplate::where('standard_value', 700)->value('id'), 'reading_value' => 700],
            ],
            'dimension_checks' => [
                ['dimension' => 'length', 'measured_value' => 54],
                ['dimension' => 'width', 'measured_value' => 27],
                ['dimension' => 'diameter', 'measured_value' => 88.5],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $test = CalibrationTest::firstOrFail();
        $this->assertSame('OK', $test->status);
        $this->assertCount(3, $test->dimensionChecks);
        $this->assertFalse($test->dimensionChecks->firstWhere('dimension', 'width')->is_within_range);
        $this->assertEquals(25.7, $test->dimensionChecks->firstWhere('dimension', 'width')->min_value);
        $specification->update(['width_min' => 20]);
        $this->assertEquals(25.7, $test->dimensionChecks()->where('dimension', 'width')->first()->min_value);
    }

    public function test_dimension_checks_must_match_specification(): void
    {
        $instrument = $this->makeInstrument();
        $specification = Specification::create([
            'name' => 'Tapak Holder',
            'length_min' => 50.7,
            'length_max' => 54,
            'dimension_unit' => 'mm',
        ]);
        $instrument->update(['specification_id' => $specification->id]);

        $response = $this->actingAs($this->inspector())->post('/tests', [
            'instrument_id' => $instrument->id,
            'test_date' => '2026-08-01',
            'items' => [
                ['standard_template_id' => StandardTemplate::where('standard_value', 500)->value('id'), 'reading_value' => 500],
                ['standard_template_id' => StandardTemplate::where('standard_value', 700)->value('id'), 'reading_value' => 700],
            ],
            'dimension_checks' => [],
        ]);

        $response->assertSessionHasErrors('dimension_checks');
        $this->assertDatabaseCount('calibration_tests', 0);
    }

    public function test_fail_when_avg_exceeds_limit(): void
    {
        $instrument = $this->makeInstrument();

        $response = $this->actingAs($this->inspector())->post('/tests', [
            'instrument_id' => $instrument->id,
            'test_date' => '2026-08-10',
            'items' => [
                ['standard_template_id' => StandardTemplate::where('standard_value', 500)->value('id'), 'reading_value' => 502],
                ['standard_template_id' => StandardTemplate::where('standard_value', 700)->value('id'), 'reading_value' => 720],
            ],
        ]);

        $test = CalibrationTest::first();
        $this->assertNotNull($test);
        $this->assertEquals('NG', $test->status);
        $this->assertEquals('2026-09-10', $test->next_test_date->toDateString());
        $this->assertTrue($test->items()->get()[0]->is_within_limit);
        $this->assertFalse($test->items()->get()[1]->is_within_limit);
        $this->assertEquals(11.0, $test->avg_correction);
    }

    public function test_pass_even_with_one_point_outside_when_avg_within(): void
    {
        $instrument = $this->makeInstrument();

        $group = $instrument->capacity->groups->first();
        foreach ([800, 1000, 1200, 1300, 1500] as $i => $value) {
            $group->standards()->create(['standard_value' => $value, 'sort_order' => $i + 2]);
        }
        $readings = [505, 702, 799, 998, 1196, 1295, 1494];
        $response = $this->actingAs($this->inspector())->post('/tests', [
            'instrument_id' => $instrument->id,
            'test_date' => '2026-08-15',
            'items' => $group->standards()->get()->map(fn ($s, $i) => ['standard_template_id' => $s->id, 'reading_value' => $readings[$i]])->all(),
        ]);
        $response->assertSessionHasNoErrors();

        $test = CalibrationTest::first();
        $this->assertNotNull($test);
        $this->assertEquals('OK', $test->status);
        $this->assertFalse($test->items()->get()[6]->is_within_limit);
        $this->assertEquals(-1.5714, $test->avg_correction);
    }

    public function test_manual_status_allows_empty_items(): void
    {
        $instrument = $this->makeInstrument();

        $response = $this->actingAs($this->inspector())->post('/tests', [
            'instrument_id' => $instrument->id,
            'test_date' => '2026-08-20',
            'status' => 'SPARE',
        ]);

        $test = CalibrationTest::first();
        $this->assertNotNull($test);
        $this->assertEquals('SPARE', $test->status);
        $this->assertNull($test->avg_correction);
        $this->assertEquals(0, $test->items()->count());
    }

    public function test_completeness_and_decimal_validation_apply_to_auto_and_overrides(): void
    {
        $instrument = $this->makeInstrument();
        $this->actingAs($this->inspector());
        $ids = StandardTemplate::pluck('id');
        $foreign = Capacity::create(['name' => 'Other', 'value' => 1, 'unit' => 'kg'])
            ->groups()->create(['name' => 'Other', 'reference_media' => 'Other'])
            ->standards()->create(['standard_value' => 1, 'sort_order' => 0]);
        foreach ([null, '', 'OK', 'NG'] as $status) {
            foreach ([
                [],
                [['standard_template_id' => $ids[0], 'reading_value' => 500]],
                [['standard_template_id' => $ids[0], 'reading_value' => 500], ['standard_template_id' => $ids[0], 'reading_value' => 700]],
                [['standard_template_id' => $ids[0], 'reading_value' => 500], ['standard_template_id' => $foreign->id, 'reading_value' => 700]],
                [['standard_template_id' => $ids[0], 'reading_value' => '500.00001'], ['standard_template_id' => $ids[1], 'reading_value' => 700]],
                [['standard_template_id' => $ids[0], 'reading_value' => '100000000'], ['standard_template_id' => $ids[1], 'reading_value' => 700]],
                [['standard_template_id' => $ids[0], 'reading_value' => 'INF'], ['standard_template_id' => $ids[1], 'reading_value' => 700]],
            ] as $items) {
                $this->post('/tests', ['instrument_id' => $instrument->id, 'test_date' => '2026-08-01', 'status' => $status, 'items' => $items])->assertSessionHasErrors();
            }
        }
        $this->assertDatabaseCount('calibration_tests', 0);
    }

    public function test_weighted_average_overrides_and_immutable_snapshots(): void
    {
        $instrument = $this->makeInstrument();
        $group = $instrument->capacity->groups()->create(['name' => 'Second', 'reference_media' => 'Reference', 'sort_order' => 1]);
        $group->standards()->create(['standard_value' => 100, 'sort_order' => 0]);
        $selectedGroupId = $instrument->capacity->groups()->orderBy('sort_order')->first()->id;
        $items = StandardTemplate::orderBy('id')->get()->map(fn ($s, $i) => [
            'standard_template_id' => $s->id,
            'reading_value' => (float) $s->standard_value + [1.0001, 2.0001, 12.0002][$i],
            'standard_value' => 999,
            'unit' => 'forged',
        ])->all();
        $this->actingAs($this->inspector());
        foreach ([null, '', 'OK', 'NG'] as $status) {
            $this->post('/tests', ['instrument_id' => $instrument->id, 'test_date' => '2026-08-01', 'status' => $status, 'items' => array_reverse($items)])
                ->assertSessionHasNoErrors();
            $test = CalibrationTest::latest('id')->first();
            $this->assertEquals(5.0001, $test->avg_correction);
            $this->assertSame('NG', $test->computed_status);
            $this->assertSame($status ?: null, $test->selected_status);
            $this->assertSame($status ?: 'NG', $test->status);
            $this->assertEquals(-5, $test->min_correction_snapshot);
            $this->assertEquals(5, $test->max_correction_snapshot);
            $this->assertEquals([0, 0, 1], $test->items->pluck('group_order')->all());
            $this->assertEquals([0, 1, 0], $test->items->pluck('point_order')->all());
            $this->assertEquals(['gr', 'gr', 'gr'], $test->items->pluck('unit')->all());
            $this->assertEquals(500, $test->items->first()->standard_value);
        }
        $instrument->capacity->groups()->delete();
        $instrument->acceptableLimit->update(['min_correction' => -99, 'max_correction' => 99, 'unit' => 'kg']);
        $this->assertDatabaseCount('calibration_test_items', 12);
        $this->assertEquals(5, $test->fresh()->max_correction_snapshot);
        $this->assertSame('Second', $test->fresh()->items->last()->group_name);
        $this->assertSame('Reference', $test->fresh()->items->last()->reference_media);
    }

    public function test_manual_statuses_ignore_even_invalid_items(): void
    {
        $instrument = $this->makeInstrument();
        $this->actingAs($this->inspector());
        foreach (['SPARE', 'NA', 'SERVICE'] as $status) {
            $this->post('/tests', ['instrument_id' => $instrument->id, 'test_date' => '2026-08-01', 'status' => $status, 'items' => 'invalid'])
                ->assertSessionHasNoErrors();
            $test = CalibrationTest::latest('id')->first();
            $this->assertSame($status, $test->status);
            $this->assertSame($status, $test->selected_status);
            $this->assertNull($test->computed_status);
            $this->assertNull($test->avg_correction);
            $this->assertCount(0, $test->items);
        }
    }

    public function test_group_sync_preserves_ids_validates_ownership_and_rolls_back(): void
    {
        $instrument = $this->makeInstrument();
        $capacity = $instrument->capacity;
        $group = $capacity->groups->first();
        $standard = $group->standards->first();
        $this->actingAs($this->inspector());
        $payload = ['name' => 'Changed', 'value' => 3, 'unit' => 'kg', 'groups' => [[
            'id' => $group->id, 'name' => 'Renamed', 'reference_media' => 'New media',
            'standards' => [['id' => $standard->id, 'standard_value' => 501]],
        ]]];
        $this->put("/masters/capacities/$capacity->id", $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('standard_templates', ['id' => $standard->id, 'standard_value' => 501]);
        $this->assertDatabaseCount('standard_templates', 1);
        $foreign = Capacity::create(['name' => 'Other', 'value' => 1, 'unit' => 'kg'])
            ->groups()->create(['name' => 'Other', 'reference_media' => 'Other']);
        $point = $foreign->standards()->create(['standard_value' => 9, 'sort_order' => 0]);
        $invalid = $payload;
        $invalid['name'] = 'Must roll back';
        $invalid['groups'][0]['standards'][0]['id'] = $point->id;
        $this->put("/masters/capacities/$capacity->id", $invalid)->assertSessionHasErrors('groups.0.standards.0.id');
        $this->assertSame('Changed', $capacity->fresh()->name);
        $invalid['groups'][0]['id'] = $foreign->id;
        $this->put("/masters/capacities/$capacity->id", $invalid)->assertSessionHasErrors('groups.0.id');
        $invalid = $payload;
        $invalid['groups'][0]['standards'] = [];
        $this->put("/masters/capacities/$capacity->id", $invalid)->assertSessionHasErrors();
        $payload['groups'] = [];
        $this->put("/masters/capacities/$capacity->id", $payload)->assertSessionHasNoErrors();
        $this->assertCount(0, $capacity->fresh()->groups);
    }

    public function test_item_failure_rolls_back_test_and_items(): void
    {
        $instrument = $this->makeInstrument();
        $items = StandardTemplate::get()->map(fn ($s) => ['standard_template_id' => $s->id, 'reading_value' => $s->standard_value])->all();
        DB::unprepared("CREATE TRIGGER reject_item BEFORE INSERT ON calibration_test_items BEGIN SELECT RAISE(ABORT, 'item failure'); END");
        $this->actingAs($this->inspector())->post('/tests', ['instrument_id' => $instrument->id, 'test_date' => '2026-08-01', 'items' => $items])->assertStatus(500);
        $this->assertDatabaseCount('calibration_tests', 0);
        $this->assertDatabaseCount('calibration_test_items', 0);
    }

    public function test_migration_preserves_legacy_ids_values_and_null_history(): void
    {
        $instrument = $this->makeInstrument();
        $legacy = CalibrationTest::create([
            'instrument_id' => $instrument->id, 'tester_id' => $this->inspector()->id,
            'test_date' => '2026-08-01', 'next_test_date' => '2026-09-01', 'status' => 'OK', 'avg_correction' => 1,
        ]);
        $legacy->items()->create(['standard_value' => 500, 'reading_value' => 501, 'correction' => 1, 'is_within_limit' => true]);
        $ids = StandardTemplate::pluck('standard_value', 'id')->all();
        $migration = require database_path('migrations/2026_09_14_000000_group_calibration_standards.php');
        $migration->down();
        $migration->up();
        $this->assertSame($ids, StandardTemplate::pluck('standard_value', 'id')->all());
        $this->assertFalse(Schema::hasColumn('standard_templates', 'capacity_id'));
        $group = $instrument->capacity->groups()->first();
        $this->assertSame('Penimbangan', $group->name);
        $this->assertSame('Anak Timbangan', $group->reference_media);
        $this->assertCount(2, $group->standards);
        $this->assertNull($legacy->fresh()->computed_status);
        $this->assertNull($legacy->fresh()->selected_status);
        $this->assertNull($legacy->fresh()->min_correction_snapshot);
        $this->assertNull($legacy->fresh()->max_correction_snapshot);
        $this->assertSame('OK', $legacy->fresh()->status);
        $this->assertEquals(1, $legacy->fresh()->avg_correction);
        foreach (['group_order', 'group_name', 'reference_media', 'unit', 'point_order'] as $field) {
            $this->assertNull($legacy->fresh()->items->first()->$field);
        }
    }

    public function test_migration_rejects_null_capacity_before_mutations(): void
    {
        $instrument = $this->makeInstrument();
        $migration = require database_path('migrations/2026_09_14_000000_group_calibration_standards.php');
        $migration->down();
        DB::table('standard_templates')->update(['capacity_id' => null]);
        try {
            $migration->up();
            $this->fail('Migration must reject null capacities.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('null or orphan', $exception->getMessage());
            $this->assertFalse(Schema::hasTable('standard_groups'));
            $this->assertFalse(Schema::hasColumn('standard_templates', 'standard_group_id'));
            $this->assertDatabaseCount('standard_templates', 2);
        } finally {
            DB::table('standard_templates')->update(['capacity_id' => $instrument->capacity_id]);
            $migration->up();
        }
    }

    public function test_group_props_and_show_snapshot_contract(): void
    {
        $instrument = $this->makeInstrument();
        $this->actingAs($this->inspector());
        $this->get('/tests/create')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tests/Create')
            ->where('instruments', []));
        $this->get('/tests/instruments/search?search=W.FL')
            ->assertOk()
            ->assertJsonPath('data.0.id', $instrument->id)
            ->assertJsonPath('data.0.code', 'W.FL.5');
        $this->get("/tests/instruments/{$instrument->id}")
            ->assertOk()
            ->assertJsonPath('data.capacity.groups.0.name', 'Penimbangan')
            ->assertJsonPath('data.capacity.groups.0.reference_media', 'Anak Timbangan')
            ->assertJsonCount(2, 'data.capacity.groups.0.standards')
            ->assertJsonPath('data.acceptable_limit.unit', 'gr');
        $this->get("/masters/capacities/{$instrument->capacity_id}/edit")->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Masters/Form')->has('item.groups.0.id')->has('item.groups.0.standards.0.id'));
        $items = StandardTemplate::get()->map(fn ($s) => ['standard_template_id' => $s->id, 'reading_value' => $s->standard_value])->all();
        $this->post('/tests', ['instrument_id' => $instrument->id, 'test_date' => '2026-08-01', 'status' => 'NG', 'items' => $items])->assertSessionHasNoErrors();
        $test = CalibrationTest::first();
        $this->get("/tests/$test->id")->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tests/Show')->where('test.computed_status', 'OK')->where('test.selected_status', 'NG')->where('test.status', 'NG')
            ->where('test.items.0.group_name', 'Penimbangan')->where('test.items.0.reference_media', 'Anak Timbangan')
            ->where('test.items.0.unit', 'gr')->where('test.items.0.group_order', 0)->where('test.items.0.point_order', 0));
    }

    public function test_history_keeps_soft_deleted_instrument(): void
    {
        $instrument = $this->makeInstrument();
        $this->actingAs($this->inspector());
        $this->post('/tests', ['instrument_id' => $instrument->id, 'test_date' => '2026-08-01', 'status' => 'SPARE'])
            ->assertSessionHasNoErrors();
        $test = CalibrationTest::first();
        $instrument->delete();
        $this->get("/tests/$test->id")->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tests/Show')->where('test.instrument.code', 'W.FL.5')->where('test.status', 'SPARE'));
    }

    public function test_database_seeder_uses_groups_and_snapshots(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('standard_groups', 4);
        $test = CalibrationTest::first();
        $this->assertSame('OK', $test->computed_status);
        $this->assertSame('OK', $test->status);
        $this->assertSame('gr', $test->items->first()->unit);
        $this->assertSame('Penimbangan', $test->items->first()->group_name);
    }

    public function test_inspector_can_edit_and_delete_calibration_test(): void
    {
        $instrument = $this->makeInstrument();
        $inspector = $this->inspector();
        $this->actingAs($inspector)->post('/tests', [
            'instrument_id' => $instrument->id,
            'test_date' => '2026-08-01',
            'items' => [
                ['standard_template_id' => StandardTemplate::where('standard_value', 500)->value('id'), 'reading_value' => 502],
                ['standard_template_id' => StandardTemplate::where('standard_value', 700)->value('id'), 'reading_value' => 704],
            ],
        ])->assertSessionHasNoErrors();
        $test = CalibrationTest::firstOrFail();
        $item = $test->items()->firstOrFail();

        $this->actingAs($inspector)->put("/tests/{$test->id}", [
            'test_date' => '2026-08-02',
            'status' => 'NG',
            'notes' => 'Diperbarui',
            'items' => $test->items->map(fn ($row) => [
                'id' => $row->id,
                'reading_value' => $row->id === $item->id ? '510' : $row->reading_value,
            ])->all(),
            'dimension_checks' => [],
        ])->assertRedirect(route('tests.show', $test));
        $test->refresh();
        $item->refresh();
        $this->assertSame('2026-08-02', $test->test_date->toDateString());
        $this->assertSame('2026-09-02', $test->next_test_date->toDateString());
        $this->assertSame('NG', $test->status);
        $this->assertSame('Diperbarui', $test->notes);
        $this->assertEquals(510, (float) $item->reading_value);
        $this->assertEquals(10, (float) $item->correction);

        $this->actingAs($inspector)->delete("/tests/{$test->id}")
            ->assertRedirect(route('tests.index'));
        $this->assertDatabaseMissing('calibration_tests', ['id' => $test->id]);
        $this->assertDatabaseMissing('calibration_test_items', ['calibration_test_id' => $test->id]);
    }

    public function test_all_roles_can_access_calibration_test_edit_and_delete(): void
    {
        $tester = $this->inspector();
        $instrument = $this->makeInstrument();
        foreach (['admin', 'inspector', 'user'] as $role) {
            $test = CalibrationTest::create([
                'instrument_id' => $instrument->id,
                'test_date' => '2026-08-01',
                'next_test_date' => '2026-09-01',
                'tester_id' => $tester->id,
                'status' => 'OK',
            ]);
            $account = $role === 'inspector' ? $tester : User::create(['name' => ucfirst($role), 'email' => "{$role}@test", 'password' => 'password']);
            if ($role !== 'inspector') {
                $account->assignRole($role);
            }

            $this->actingAs($account)->get("/tests/{$test->id}/edit")->assertOk();
            $this->actingAs($account)->delete("/tests/{$test->id}")->assertRedirect(route('tests.index'));
        }
    }

    public function test_permission_middleware_blocks_inspector_from_report_export(): void
    {
        $this->actingAs($this->inspector())
            ->get('/reports')
            ->assertForbidden();
    }
}
