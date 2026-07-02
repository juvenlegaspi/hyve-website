<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_log_in_through_the_admin_login_page(): void
    {
        $admin = User::factory()->create([
            'username' => 'hyveadmin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123'),
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->post(route('admin.login.store'), [
            'login' => 'hyveadmin',
            'password' => 'Password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_member_cannot_log_in_through_the_admin_login_page(): void
    {
        User::factory()->create([
            'username' => 'memberonly',
            'email' => 'member@example.com',
            'password' => Hash::make('Password123'),
            'role' => User::ROLE_MEMBER,
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'login' => 'memberonly',
            'password' => 'Password123',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_only_the_super_admin_can_access_the_users_page(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }
}
