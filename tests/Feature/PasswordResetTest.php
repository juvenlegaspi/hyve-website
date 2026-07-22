<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_is_available_from_the_login_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'))
            ->assertSee('Forgot password?');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee('Send reset link');
    }

    public function test_an_active_member_receives_a_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'member@example.com',
            'status' => 0,
        ]);

        $response = $this->post(route('password.email'), [
            'email' => '  MEMBER@EXAMPLE.COM ',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_and_inactive_accounts_receive_the_same_safe_response_without_email(): void
    {
        Notification::fake();

        User::factory()->create([
            'email' => 'inactive@example.com',
            'status' => 1,
        ]);

        $unknownResponse = $this->post(route('password.email'), [
            'email' => 'unknown@example.com',
        ]);
        $inactiveResponse = $this->post(route('password.email'), [
            'email' => 'inactive@example.com',
        ]);

        $unknownResponse->assertSessionHas('status');
        $inactiveResponse->assertSessionHas('status');
        $this->assertSame(
            $unknownResponse->getSession()->get('status'),
            $inactiveResponse->getSession()->get('status')
        );
        Notification::assertNothingSent();
    }

    public function test_a_member_can_reset_the_password_with_a_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => Hash::make('OldPassword123'),
        ]);

        $this->post(route('password.email'), ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertTrue(Hash::check('NewPassword456', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();
    }

    public function test_an_invalid_token_cannot_change_the_password(): void
    {
        $user = User::factory()->create([
            'email' => 'invalid-token@example.com',
            'password' => Hash::make('OldPassword123'),
        ]);

        $response = $this->from(route('password.reset', ['token' => 'invalid-token']))
            ->post(route('password.store'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'NewPassword456',
                'password_confirmation' => 'NewPassword456',
            ]);

        $response->assertRedirect(route('password.reset', ['token' => 'invalid-token']));
        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }
}
