<?php

namespace Modules\Users\Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserProfileSerializationTest extends TestCase
{
    public function test_user_serialization_does_not_expose_credentials(): void
    {
        $user = new User([
            'name' => 'Taylor User',
            'email' => 'taylor@example.com',
            'password' => 'secret-password',
        ]);
        $user->setAttribute('remember_token', 'secret-remember-token');

        $serialized = $user->toArray();

        $this->assertSame('Taylor User', $serialized['name']);
        $this->assertSame('taylor@example.com', $serialized['email']);
        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
    }
}
