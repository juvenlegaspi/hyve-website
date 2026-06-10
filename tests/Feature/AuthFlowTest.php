<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_can_register_and_is_redirected_to_bookings(): void
    {
        $response = $this->post(route('register.store'), [
            'username' => 'hyveclient01',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.com',
            'phone' => '+639181112222',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'username' => 'hyveclient01',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.com',
            'phone' => '+639181112222',
        ]);
    }

    public function test_a_user_can_log_in_using_username(): void
    {
        $user = User::factory()->create([
            'username' => 'hyveboss',
            'password' => Hash::make('Password123'),
        ]);

        $response = $this->post(route('login.store'), [
            'login' => 'hyveboss',
            'password' => 'Password123',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $this->assertAuthenticatedAs($user);
    }
}
