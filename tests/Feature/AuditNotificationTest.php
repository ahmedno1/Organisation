<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_creation_logs_activity_and_creates_notifications(): void
    {
        $institution = Institution::query()->create(['name' => 'Inst N']);
        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);

        $payload = [
            'head_name' => 'Test User Full Name',
            'head_national_id' => '123456789',
            'head_gender' => 'male',
            'head_birth_date' => '1990-01-01',
            'marital_status' => 'single',
            'family_members_count' => 1,
        ];

        $this->actingAs($admin)->post(route('families.store'), $payload)->assertRedirect(route('families.index'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'family.created',
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseCount('notifications', 1);
        $developer->refresh();
        $this->assertCount(1, $developer->unreadNotifications);
    }

    public function test_activity_logs_page_is_accessible_for_developer(): void
    {
        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        ActivityLog::query()->create(['action' => 'test.action']);

        $this->actingAs($developer)->get(route('activity_logs.index'))->assertOk();
    }
}
