<?php

namespace Modules\Users\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Identity\Notifications\VerifyEmailNotification;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_read_update_and_delete_are_limited_to_the_authenticated_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'name' => 'Taylor User',
            'email_verified_at' => now(),
        ]);
        $otherUser = User::factory()->create();

        $this->getJson('/api/v1/profile')
            ->assertUnauthorized();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/profile', [
                'name' => 'Taylor Updated',
                'email' => 'updated@example.com',
                'email_verified_at' => now()->toISOString(),
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Taylor Updated')
            ->assertJsonPath('user.email', 'updated@example.com')
            ->assertJsonPath('user.email_verified_at', null);

        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Taylor Updated',
            'email' => 'updated@example.com',
            'email_verified_at' => null,
        ]);
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/profile')
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
    }
}
