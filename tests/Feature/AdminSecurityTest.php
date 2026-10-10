<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_own_password_only(): void
    {
        $institution = Institution::query()->create(['name' => 'Institution A']);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
            'password' => 'old-password',
        ]);
        $originalEmail = $admin->email;

        $response = $this->actingAs($admin)->put(route('admin.security.password.update'), [
            'email' => 'admin-updated@example.com',
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect();
        $admin->refresh();

        $this->assertSame($originalEmail, $admin->email);
        $this->assertTrue(Hash::check('new-password-123', $admin->password));
    }
}
