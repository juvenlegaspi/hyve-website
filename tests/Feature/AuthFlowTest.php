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

        $this->assertDatabaseHas('booking_users', [
            'username' => 'hyveclient01',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.com',
            'number' => '+639181112222',
        ]);
    }

    public function test_registration_normalizes_username_email_and_name_spacing(): void
    {
        $response = $this->post(route('register.store'), [
            'username' => '  HYVE_CLIENT_02  ',
            'first_name' => '  Maria   Clara ',
            'last_name' => '  Dela   Cruz ',
            'email' => '  MARIA.CLARA@EXAMPLE.COM ',
            'phone' => '  +63 918 111 3333  ',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('booking_users', [
            'username' => 'hyve_client_02',
            'first_name' => 'Maria Clara',
            'last_name' => 'Dela Cruz',
            'email' => 'maria.clara@example.com',
            'number' => '+63 918 111 3333',
        ]);
    }

    public function test_registration_rejects_invalid_phone_numbers(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'username' => 'hyveclient03',
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'email' => 'ana@example.com',
            'phone' => 'invalid-phone###',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
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

    public function test_a_user_can_log_in_using_email(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('Password123'),
        ]);

        $response = $this->post(route('login.store'), [
            'login' => 'member@example.com',
            'password' => 'Password123',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $this->assertAuthenticatedAs($user);
    }
}
