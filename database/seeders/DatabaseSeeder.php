<?php

namespace Database\Seeders;

use App\Models\AcceptableLimit;
use App\Models\Brand;
use App\Models\CalibrationTest;
use App\Models\CalibrationTestItem;
use App\Models\Capacity;
use App\Models\Department;
use App\Models\Factory;
use App\Models\Instrument;
use App\Models\InstrumentType;
use App\Models\Specification;
use App\Models\StandardTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $factories = collect([
            ['name' => 'PT Karya Inti Mandiri'],
            ['name' => 'Pabrik Dalu 1'],
            ['name' => 'Pabrik Dalu 2'],
        ])->map(fn ($f) => Factory::create($f));

        foreach ($factories as $factory) {
            foreach (['Filling', 'Packing', 'Maintenance'] as $name) {
                Department::create([
                    'factory_id' => $factory->id,
                    'name' => $name,
                ]);
            }
        }

        $typeNames = ['Timbangan', 'Caliper', 'Rol Siku', 'Thermo Hunter', 'Stopwatch', 'Laser'];
        foreach ($typeNames as $name) {
            InstrumentType::create(['name' => $name]);
        }

        foreach (['DICSON', 'Mitutoyo', 'Krisbow'] as $name) {
            Brand::create(['name' => $name]);
        }

        foreach (['Kapasitas 3 kg', 'Ketelitian 0.1', 'Bahan stainless', 'Tipe analog'] as $name) {
            Specification::create(['name' => $name]);
        }

        $capacityData = [
            ['name' => '3 KG', 'value' => 3, 'unit' => 'kg', 'standards' => [500, 700, 800, 1000]],
            ['name' => '500 KG', 'value' => 500, 'unit' => 'kg', 'standards' => [100, 250, 500, 750, 1000]],
            ['name' => '150 MM', 'value' => 150, 'unit' => 'mm', 'standards' => [10, 20, 30, 50]],
            ['name' => '2 KG', 'value' => 2, 'unit' => 'kg', 'standards' => [200, 400, 600, 800]],
        ];

        foreach ($capacityData as $c) {
            $capacity = Capacity::create([
                'name' => $c['name'],
                'value' => $c['value'],
                'unit' => $c['unit'],
            ]);
            $group = $capacity->groups()->create([
                'name' => $c['unit'] === 'mm' ? 'Pengukuran' : 'Penimbangan',
                'reference_media' => $c['unit'] === 'mm' ? 'Gauge Block' : 'Anak Timbangan',
                'sort_order' => 0,
            ]);
            foreach ($c['standards'] as $i => $standard) {
                StandardTemplate::create([
                    'standard_group_id' => $group->id,
                    'standard_value' => $standard,
                    'sort_order' => $i,
                ]);
            }
        }

        foreach ([
            ['name' => '±5 gr', 'min_correction' => -5, 'max_correction' => 5, 'unit' => 'gr'],
            ['name' => '±200 gr', 'min_correction' => -200, 'max_correction' => 200, 'unit' => 'gr'],
            ['name' => '±2 kg', 'min_correction' => -2, 'max_correction' => 2, 'unit' => 'kg'],
            ['name' => '±0.5 mm', 'min_correction' => -0.5, 'max_correction' => 0.5, 'unit' => 'mm'],
        ] as $l) {
            AcceptableLimit::create($l);
        }

        $superAdmin = User::create([
            'name' => 'Kicky Maulana',
            'email' => 'kickymaulana@gmail.com',
            'password' => 'password123',
            'factory_id' => $factories->first()->id,
            'nik' => 'D260065',
            'is_approved' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        $firstDept = Department::first();
        $timbangan = InstrumentType::where('name', 'Timbangan')->first();
        $caliper = InstrumentType::where('name', 'Caliper')->first();

        Instrument::create([
            'code' => 'W.FL.5',
            'factory_id' => $factories->first()->id,
            'department_id' => $firstDept->id,
            'instrument_type_id' => $timbangan->id,
            'brand_id' => Brand::first()->id,
            'capacity_id' => Capacity::first()->id,
            'acceptable_limit_id' => AcceptableLimit::first()->id,
            'specification_id' => Specification::first()?->id,
            'notes' => 'Timbangan contoh',
        ]);

        Instrument::create([
            'code' => 'W.PC.12',
            'factory_id' => $factories->first()->id,
            'department_id' => Department::where('name', 'Packing')->first()->id,
            'instrument_type_id' => $caliper->id,
            'brand_id' => Brand::where('name', 'Mitutoyo')->first()->id,
            'capacity_id' => Capacity::where('value', 150)->first()->id,
            'acceptable_limit_id' => AcceptableLimit::where('name', '±0.5 mm')->first()->id,
        ]);

        $instrument = Instrument::where('code', 'W.FL.5')->first();
        $test = CalibrationTest::create([
            'instrument_id' => $instrument->id,
            'test_date' => now()->subMonth()->toDateString(),
            'next_test_date' => now()->subMonth()->addMonth()->toDateString(),
            'tester_id' => $superAdmin->id,
            'status' => 'OK',
            'computed_status' => 'OK',
            'avg_correction' => 1,
            'min_correction_snapshot' => $instrument->acceptableLimit->min_correction,
            'max_correction_snapshot' => $instrument->acceptableLimit->max_correction,
        ]);
        foreach ($instrument->capacity->groups->first()->standards as $s) {
            CalibrationTestItem::create([
                'calibration_test_id' => $test->id,
                'standard_value' => $s->standard_value,
                'reading_value' => $s->standard_value + 1,
                'correction' => 1,
                'is_within_limit' => true,
                'group_order' => 0,
                'group_name' => $s->group->name,
                'reference_media' => $s->group->reference_media,
                'unit' => $instrument->acceptableLimit->unit,
                'point_order' => $s->sort_order,
            ]);
        }
    }
}
