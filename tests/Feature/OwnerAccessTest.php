<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_has_a_dedicated_login_and_is_sent_to_the_dashboard(): void
    {
        $owner = User::factory()->create([
            'username' => 'hyveowner',
            'email' => 'owner@hyve.test',
            'password' => Hash::make('OwnerPassword123'),
            'role' => User::ROLE_OWNER,
            'status' => 0,
        ]);

        $this->get(route('owner.login'))
            ->assertOk()
            ->assertSee('HYVE Owner Portal')
            ->assertSee('Private executive access');

        $this->post(route('owner.login.store'), [
            'login' => 'hyveowner',
            'password' => 'OwnerPassword123',
        ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($owner);
    }

    public function test_only_the_owner_account_can_use_the_owner_login(): void
    {
        User::factory()->create([
            'username' => 'regularadmin',
            'password' => Hash::make('Password123'),
            'role' => User::ROLE_ADMIN,
            'status' => 0,
        ]);

        $this->from(route('owner.login'))
            ->post(route('owner.login.store'), [
                'login' => 'regularadmin',
                'password' => 'Password123',
            ])
            ->assertRedirect(route('owner.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_owner_credentials_are_rejected_by_the_regular_admin_login(): void
    {
        User::factory()->create([
            'username' => 'hyveowner',
            'password' => Hash::make('OwnerPassword123'),
            'role' => User::ROLE_OWNER,
            'status' => 0,
        ]);

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'login' => 'hyveowner',
                'password' => 'OwnerPassword123',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'login' => 'hyveowner',
                'password' => 'OwnerPassword123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_owner_can_only_view_dashboard_sales_monitoring_and_reports(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Owner Portal');

        $this->actingAs($owner)
            ->get(route('admin.sales-monitoring.index'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('admin.sections.reports'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('admin.bookings.index'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('admin.sections.payments'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('admin.sections.admin-roles'))
            ->assertForbidden();

        $this->assertTrue($owner->hasPermission('dashboard.view'));
        $this->assertTrue($owner->hasPermission('sales_monitoring.view'));
        $this->assertTrue($owner->hasPermission('reports.view'));
        $this->assertFalse($owner->hasPermission('bookings.view'));
        $this->assertFalse($owner->hasPermission('payments.view'));
    }

    public function test_only_one_owner_account_can_be_created(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($superAdmin)
            ->from(route('admin.sections.admin-roles'))
            ->post(route('admin.admin-roles.store'), [
                'username' => 'secondowner',
                'first_name' => 'Second',
                'last_name' => 'Owner',
                'email' => 'second-owner@hyve.test',
                'phone' => '09171234567',
                'password' => 'OwnerPassword123',
                'role' => User::ROLE_OWNER,
            ])
            ->assertRedirect(route('admin.sections.admin-roles'))
            ->assertSessionHasErrors('role', null, 'adminRoleStore');

        $this->assertSame(1, User::query()->where('role', User::ROLE_OWNER)->count());
    }

    public function test_super_admin_can_create_the_owner_account(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->post(route('admin.admin-roles.store'), [
                'username' => 'hyveowner',
                'first_name' => 'HYVE',
                'last_name' => 'Owner',
                'email' => 'owner@hyve.test',
                'phone' => '09171234567',
                'password' => 'OwnerPassword123',
                'role' => User::ROLE_OWNER,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('admin_role_success');

        $owner = User::query()->where('role', User::ROLE_OWNER)->firstOrFail();

        $this->assertSame('hyveowner', $owner->username);
        $this->assertTrue(Hash::check('OwnerPassword123', $owner->password));

        $this->actingAs($superAdmin)
            ->get(route('admin.sections.admin-roles'))
            ->assertOk()
            ->assertSee('Copy Owner Link')
            ->assertSee('https://hyvecoworkingspace.com/owner/login');
    }
}
