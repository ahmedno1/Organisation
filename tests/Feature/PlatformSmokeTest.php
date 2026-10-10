<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Family;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_access_core_pages(): void
    {
        [$developer, $admin, $dataEntry, $family] = $this->prepareData();

        $this->actingAs($developer)->get(route('dashboard'))->assertOk();
        $this->actingAs($developer)->get(route('developers.administrators.index'))->assertOk();
        $this->actingAs($developer)->get(route('developers.administrators.create'))->assertOk();
        $this->actingAs($developer)->get(route('developers.health.index'))->assertOk();
        $this->actingAs($developer)->get(route('families.index'))->assertOk();
        $this->actingAs($developer)->get(route('families.create'))->assertOk();
        $this->actingAs($developer)->get(route('families.edit', $family))->assertOk();
        $this->actingAs($developer)->get(route('reports.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($developer)->get(route('reports.print'))->assertOk();
        $this->actingAs($developer)->get(route('benefits.index'))->assertOk();

        $this->actingAs($developer)->get(route('admin.data-entry-users.index'))->assertForbidden();
    }

    public function test_admin_can_access_own_scope_pages(): void
    {
        [$developer, $admin, $dataEntry, $family] = $this->prepareData();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.data-entry-users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.data-entry-users.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.data-entry-users.edit', $dataEntry))->assertOk();
        $this->actingAs($admin)->get(route('families.index'))->assertOk();
        $this->actingAs($admin)->get(route('families.create'))->assertOk();
        $this->actingAs($admin)->get(route('families.edit', $family))->assertOk();
        $this->actingAs($admin)->get(route('reports.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($admin)->get(route('reports.print'))->assertOk();
        $this->actingAs($admin)->get(route('benefits.index'))->assertOk();

        $this->actingAs($admin)->get(route('developers.administrators.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('developers.health.index'))->assertForbidden();
    }

    public function test_data_entry_has_limited_access_and_can_access_own_family_pages(): void
    {
        [$developer, $admin, $dataEntry, $family] = $this->prepareData();

        $this->actingAs($dataEntry)->get(route('dashboard'))->assertOk();
        $this->actingAs($dataEntry)->get(route('families.index'))->assertOk();
        $this->actingAs($dataEntry)->get(route('families.create'))->assertOk();
        $this->actingAs($dataEntry)->get(route('families.edit', $family))->assertOk();

        $this->actingAs($dataEntry)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($dataEntry)->get(route('reports.print'))->assertForbidden();
        $this->actingAs($dataEntry)->get(route('benefits.index'))->assertForbidden();
        $this->actingAs($dataEntry)->get(route('admin.data-entry-users.index'))->assertForbidden();
        $this->actingAs($dataEntry)->get(route('developers.administrators.index'))->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Family}
     */
    private function prepareData(): array
    {
        $institution = Institution::query()->create([
            'name' => 'Institution Smoke',
        ]);

        $developer = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
        ]);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);

        $dataEntry = User::factory()->create([
            'role' => User::ROLE_DATA_ENTRY,
            'institution_id' => $institution->id,
            'created_by' => $admin->id,
        ]);

        Benefit::query()->create([
            'name' => 'Benefit Smoke',
        ]);

        $family = Family::query()->create([
            'institution_id' => $institution->id,
            'administrator_id' => $admin->id,
            'data_entry_id' => $dataEntry->id,
            'head_name' => 'Head Smoke',
            'head_national_id' => '123456789',
            'family_members_count' => 1,
        ]);

        return [$developer, $admin, $dataEntry, $family];
    }
}
