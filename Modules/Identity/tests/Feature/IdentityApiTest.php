<?php

namespace Modules\Identity\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Modules\Identity\Notifications\PasswordChangedNotification;
use Modules\Identity\Notifications\ResetPasswordNotification;
use Modules\Identity\Notifications\VerifyEmailNotification;
use Tests\TestCase;

class IdentityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_verification_and_unverified_users_cannot_log_in(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/register', [
            'name' => 'Taylor User',
            'email' => 'taylor@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ])->assertCreated();

        $user = User::where('email', 'taylor@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->postJson('/api/v1/login', [
            'email' => 'taylor@example.com',
            'password' => 'password-123',
        ])->assertUnprocessable();
    }

    public function test_verified_user_can_log_in_use_the_token_and_log_out(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => 'password-123',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password-123',
        ])->assertOk();

        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/v1/profile')->assertOk();
        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_signed_verification_link_verifies_the_matching_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $url = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(10),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('message', 'Email address verified.');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_forgot_password_is_generic_and_queues_a_reset_email_for_existing_users(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'If an account exists for that email, a password reset email has been sent.',
            );

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $this->postJson('/api/v1/forgot-password', ['email' => 'missing@example.com'])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'If an account exists for that email, a password reset email has been sent.',
            );
    }

    public function test_password_change_requires_current_password_and_sends_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => 'old-password',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk();

        $this->assertTrue(password_verify('new-password', $user->fresh()->password));
        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }
}
