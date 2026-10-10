<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeveloperSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_reset_own_password(): void
    {
        $developer = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($developer)->put(route('developers.security.password.update'), [
            'email' => $developer->email,
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect();
        $developer->refresh();
        $this->assertTrue(Hash::check('new-password-123', $developer->password));
    }

    public function test_developer_can_update_own_email_from_security_form(): void
    {
        $developer = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'password' => 'old-password',
        ]);

        $this->actingAs($developer)->put(route('developers.security.password.update'), [
            'email' => 'developer-updated@example.com',
            'current_password' => 'old-password',
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionDoesntHaveErrors();

        $developer->refresh();
        $this->assertSame('developer-updated@example.com', $developer->email);
    }
}
