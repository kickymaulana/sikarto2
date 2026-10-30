<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(string $role, array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name' => 'User '.$role,
            'email' => $role.'@test',
            'password' => 'password',
            'is_approved' => true,
        ], $attrs));
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_can_approve_pending_user(): void
    {
        $admin = $this->makeUser('super_admin');
        $pending = $this->makeUser('inspector', [
            'email' => 'pending@test',
            'nik' => 'NIK123',
            'is_approved' => false,
            'requested_role' => 'inspector',
        ]);

        $this->actingAs($admin)
            ->post('/users/'.$pending->id.'/approve', ['role' => 'inspector'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($pending->fresh()->is_approved);
        $this->assertTrue($pending->fresh()->hasRole('inspector'));
    }

    public function test_inspector_cannot_access_user_management(): void
    {
        $inspector = $this->makeUser('inspector');

        $this->actingAs($inspector)
            ->get('/users')
            ->assertForbidden();
    }
}
