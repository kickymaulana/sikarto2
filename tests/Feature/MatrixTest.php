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
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Tests\TestCase;

class MatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUserWithRole(string $role): User
    {
        $user = User::create([
            'nik' => 'D'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'is_approved' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeAcceptableLimit(): AcceptableLimit
    {
        return AcceptableLimit::create([
            'name' => '±5 g',
            'min_correction' => -5,
            'max_correction' => 5,
            'unit' => 'g',
        ]);
    }

    private function makeFactoryAndDept(): array
    {
        $factory = Factory::create(['name' => 'Plant 1']);
        $dept = Department::create(['name' => 'QA', 'factory_id' => $factory->id]);

        return [$factory, $dept];
    }

    private function makeInstrument(array $overrides): Instrument
    {
        [$factory, $dept] = $this->makeFactoryAndDept();
        $limit = $this->makeAcceptableLimit();
        $brand = Brand::create(['name' => 'Merk '.uniqid()]);
        $capacity = Capacity::create(['name' => '100g', 'value' => 100, 'unit' => 'g']);

        return Instrument::create(array_merge([
            'factory_id' => $factory->id,
            'department_id' => $dept->id,
            'acceptable_limit_id' => $limit->id,
            'brand_id' => $brand->id,
            'capacity_id' => $capacity->id,
        ], $overrides));
    }

    public function test_matrix_page_renders_with_default_timbangan_digital_filter(): void
    {
        $user = $this->makeUserWithRole('inspector');
        $timbangan = InstrumentType::create(['name' => 'Timbangan Digital']);
        $caliper = InstrumentType::create(['name' => 'Caliper']);

        $this->makeInstrument(['code' => 'TD-001', 'name' => 'Timbangan A', 'instrument_type_id' => $timbangan->id]);
        $this->makeInstrument(['code' => 'CL-001', 'name' => 'Caliper A', 'instrument_type_id' => $caliper->id]);

        $response = $this->actingAs($user)->get('/laporan/matrix');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Laporan/Matrix')
            ->where('typeId', $timbangan->id)
            ->has('rows', 1)
            ->where('rows.0.code', 'TD-001')
        );
    }

    public function test_matrix_filters_by_type_id_query(): void
    {
        $user = $this->makeUserWithRole('inspector');
        $timbangan = InstrumentType::create(['name' => 'Timbangan Digital']);
        $caliper = InstrumentType::create(['name' => 'Caliper']);

        $this->makeInstrument(['code' => 'TD-001', 'name' => 'Timbangan A', 'instrument_type_id' => $timbangan->id]);
        $this->makeInstrument(['code' => 'CL-001', 'name' => 'Caliper A', 'instrument_type_id' => $caliper->id]);

        $response = $this->actingAs($user)->get('/laporan/matrix?type_id='.$caliper->id);
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('typeId', $caliper->id)
            ->has('rows', 1)
            ->where('rows.0.code', 'CL-001')
        );
    }

    public function test_matrix_filters_by_factory_id_query(): void
    {
        $user = $this->makeUserWithRole('inspector');
        $timbangan = InstrumentType::create(['name' => 'Timbangan Digital']);
        [$factory] = $this->makeFactoryAndDept();
        $otherFactory = Factory::create(['name' => 'Plant 2']);
        $otherDept = Department::create(['name' => 'QA', 'factory_id' => $otherFactory->id]);

        $this->makeInstrument([
            'code' => 'P1-001',
            'name' => 'Alat Plant 1',
            'instrument_type_id' => $timbangan->id,
            'factory_id' => $factory->id,
        ]);
        $this->makeInstrument([
            'code' => 'P2-001',
            'name' => 'Alat Plant 2',
            'instrument_type_id' => $timbangan->id,
            'factory_id' => $otherFactory->id,
            'department_id' => $otherDept->id,
        ]);

        $response = $this->actingAs($user)->get('/laporan/matrix?factory_id='.$factory->id);
        $response->assertInertia(fn ($page) => $page
            ->where('factoryId', $factory->id)
            ->has('factories')
            ->has('rows', 1)
            ->where('rows.0.code', 'P1-001')
        );
    }

    public function test_matrix_sorts_naturally_by_code(): void
    {
        $user = $this->makeUserWithRole('inspector');
        $timbangan = InstrumentType::create(['name' => 'Timbangan Digital']);

        foreach (['W.FL.20', 'W.FL.3', 'W.FL.1', 'W.FL.10', 'W.FL.2'] as $code) {
            $this->makeInstrument([
                'code' => $code,
                'name' => 'Alat '.$code,
                'instrument_type_id' => $timbangan->id,
            ]);
        }

        $response = $this->actingAs($user)->get('/laporan/matrix?type_id='.$timbangan->id);
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('rows.0.code', 'W.FL.1')
            ->where('rows.1.code', 'W.FL.2')
            ->where('rows.2.code', 'W.FL.3')
            ->where('rows.3.code', 'W.FL.10')
            ->where('rows.4.code', 'W.FL.20')
        );
    }

    public function test_matrix_shows_next_date_in_following_month_only(): void
    {
        $user = $this->makeUserWithRole('inspector');
        $timbangan = InstrumentType::create(['name' => 'Timbangan Digital']);
        $instrument = $this->makeInstrument([
            'code' => 'W.FL.28',
            'name' => 'Alat 28',
            'instrument_type_id' => $timbangan->id,
        ]);

        CalibrationTest::create([
            'instrument_id' => $instrument->id,
            'test_date' => '2026-01-28',
            'next_test_date' => '2026-02-28',
            'tester_id' => $user->id,
            'status' => 'OK',
        ]);

        $response = $this->actingAs($user)->get('/laporan/matrix?type_id='.$timbangan->id.'&year=2026');

        $response->assertInertia(fn ($page) => $page
            ->where('rows.0.test_cell.1.day', '28')
            ->where('rows.0.next_cell.2.day', '28')
            ->where('rows.0.next_cell.3.day', '')
        );
    }

    public function test_matrix_shows_next_date_in_next_year(): void
    {
        $user = $this->makeUserWithRole('inspector');
        $timbangan = InstrumentType::create(['name' => 'Timbangan Digital']);
        $instrument = $this->makeInstrument([
            'code' => 'W.FL.12',
            'name' => 'Alat 12',
            'instrument_type_id' => $timbangan->id,
        ]);

        CalibrationTest::create([
            'instrument_id' => $instrument->id,
            'test_date' => '2026-12-28',
            'next_test_date' => '2027-01-28',
            'tester_id' => $user->id,
            'status' => 'OK',
        ]);

        $response = $this->actingAs($user)->get('/laporan/matrix?type_id='.$timbangan->id.'&year=2027');

        $response->assertInertia(fn ($page) => $page
            ->where('rows.0.test_cell.12.day', '')
            ->where('rows.0.next_cell.1.day', '28')
            ->where('rows.0.next_cell.2.day', '')
        );
    }

    public function test_matrix_export_returns_excel(): void
    {
        $user = $this->makeUserWithRole('admin');
        $timbangan = InstrumentType::create(['name' => 'Timbangan Digital']);
        $this->makeInstrument(['code' => 'W.FL.1', 'name' => 'Alat A', 'instrument_type_id' => $timbangan->id]);

        $response = $this->actingAs($user)->get('/laporan/matrix/export?type_id='.$timbangan->id.'&year=2026');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $filename = $response->headers->get('content-disposition');
        $this->assertStringContainsString('Matriks_Uji_Timbangan-Digital-2026.xlsx', $filename);

        $content = $response->streamedContent();
        $tmp = tempnam(sys_get_temp_dir(), 'matrix').'.xlsx';
        file_put_contents($tmp, $content);
        $reader = new Xlsx;
        $sheet = $reader->load($tmp)->getSheet(0);
        $this->assertSame('Kode Alat', $sheet->getCell('A1')->getValue());
        $this->assertSame('W.FL.1', $sheet->getCell('A2')->getValue());
        $this->assertSame('Jan Next', $sheet->getCell('E1')->getValue());
        $this->assertSame('—', $sheet->getCell('E2')->getValue());
        $this->assertSame('Jan Uji', $sheet->getCell('F1')->getValue());
        $this->assertSame('—', $sheet->getCell('F2')->getValue());
        unlink($tmp);
    }

    public function test_matrix_export_requires_authentication(): void
    {
        $response = $this->get('/laporan/matrix/export');
        $response->assertRedirect();
    }
}
