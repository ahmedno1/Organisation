<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Family;
use App\Models\FamilyAttachment;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_open_create_pages_without_runtime_errors(): void
    {
        $developer = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'institution_id' => null,
        ]);

        $institution = Institution::query()->create(['name' => 'Institution A']);
        User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);
        Benefit::query()->create(['name' => 'Benefit A']);

        $this->actingAs($developer)
            ->get(route('developers.administrators.create'))
            ->assertOk();

        $this->actingAs($developer)
            ->get(route('families.create'))
            ->assertOk();
    }

    public function test_admin_can_open_data_entry_and_family_pages(): void
    {
        $institution = Institution::query()->create(['name' => 'Institution A']);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);
        Benefit::query()->create(['name' => 'Benefit A']);

        $this->actingAs($admin)
            ->get(route('admin.data-entry-users.create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('families.create'))
            ->assertOk();
    }

    public function test_data_entry_can_access_other_data_entry_family_in_same_institution(): void
    {
        $institution = Institution::query()->create(['name' => 'Institution A']);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);

        $entryA = User::factory()->create([
            'role' => User::ROLE_DATA_ENTRY,
            'institution_id' => $institution->id,
            'created_by' => $admin->id,
        ]);

        $entryB = User::factory()->create([
            'role' => User::ROLE_DATA_ENTRY,
            'institution_id' => $institution->id,
            'created_by' => $admin->id,
        ]);

        $family = Family::query()->create([
            'institution_id' => $institution->id,
            'administrator_id' => $admin->id,
            'data_entry_id' => $entryA->id,
            'head_name' => 'Head',
            'head_national_id' => '12345',
            'family_members_count' => 1,
        ]);

        $this->actingAs($entryB)
            ->get(route('families.edit', $family))
            ->assertOk();
    }

    public function test_data_entry_without_view_all_families_permission_cannot_access_other_data_entry_family(): void
    {
        $institution = Institution::query()->create(['name' => 'Institution A']);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);

        $entryA = User::factory()->create([
            'role' => User::ROLE_DATA_ENTRY,
            'institution_id' => $institution->id,
            'created_by' => $admin->id,
            'permissions' => [
                'manage_families' => true,
                'view_all_families' => false,
            ],
        ]);

        $entryB = User::factory()->create([
            'role' => User::ROLE_DATA_ENTRY,
            'institution_id' => $institution->id,
            'created_by' => $admin->id,
            'permissions' => [
                'manage_families' => true,
                'view_all_families' => false,
            ],
        ]);

        $family = Family::query()->create([
            'institution_id' => $institution->id,
            'administrator_id' => $admin->id,
            'data_entry_id' => $entryA->id,
            'head_name' => 'Head',
            'head_national_id' => '12345',
            'family_members_count' => 1,
        ]);

        $this->actingAs($entryB)
            ->get(route('families.edit', $family))
            ->assertForbidden();
    }

    public function test_admin_update_keeps_data_entry_in_same_institution(): void
    {
        $institutionA = Institution::query()->create(['name' => 'Institution A']);
        $institutionB = Institution::query()->create(['name' => 'Institution B']);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institutionA->id,
        ]);

        $dataEntry = User::factory()->create([
            'role' => User::ROLE_DATA_ENTRY,
            'institution_id' => $institutionB->id,
            'created_by' => $admin->id,
            'permissions' => ['manage_families' => true],
        ]);

        $this->actingAs($admin)
            ->put(route('admin.data-entry-users.update', $dataEntry), [
                'name' => 'Updated Entry',
                'email' => $dataEntry->email,
                'permissions' => [
                    'manage_families' => true,
                    'view_all_families' => true,
                    'edit_families' => true,
                    'delete_families' => false,
                    'export_reports' => false,
                    'import_families' => false,
                ],
            ])
            ->assertRedirect(route('admin.data-entry-users.index'));

        $this->assertSame(
            (int) $institutionA->id,
            (int) $dataEntry->fresh()->institution_id
        );
    }

    public function test_admin_cannot_see_or_access_other_institution_families(): void
    {
        $institutionA = Institution::query()->create(['name' => 'Institution A']);
        $institutionB = Institution::query()->create(['name' => 'Institution B']);

        $adminA = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institutionA->id,
        ]);

        $adminB = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institutionB->id,
        ]);

        $familyA = Family::query()->create([
            'institution_id' => $institutionA->id,
            'administrator_id' => $adminA->id,
            'head_name' => 'Head A',
            'head_national_id' => '111111111',
            'marital_status' => 'متزوج',
            'family_members_count' => 1,
        ]);

        $familyB = Family::query()->create([
            'institution_id' => $institutionB->id,
            'administrator_id' => $adminB->id,
            'head_name' => 'Head B',
            'head_national_id' => '222222222',
            'marital_status' => 'متزوج',
            'family_members_count' => 1,
        ]);

        $this->actingAs($adminA)
            ->get(route('families.index'))
            ->assertOk()
            ->assertSee('Head A')
            ->assertDontSee('Head B');

        $this->actingAs($adminA)
            ->get(route('families.edit', $familyB))
            ->assertForbidden();
    }

    public function test_data_entry_without_view_all_families_cannot_download_attachment_from_other_data_entry_family(): void
    {
        Storage::fake('local');

        $institution = Institution::query()->create(['name' => 'Institution A']);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);

        $entryA = User::factory()->create([
            'role' => User::ROLE_DATA_ENTRY,
            'institution_id' => $institution->id,
            'created_by' => $admin->id,
            'permissions' => [
                'manage_families' => true,
                'view_all_families' => false,
            ],
        ]);

        $entryB = User::factory()->create([
            'role' => User::ROLE_DATA_ENTRY,
            'institution_id' => $institution->id,
            'created_by' => $admin->id,
            'permissions' => [
                'manage_families' => true,
                'view_all_families' => false,
            ],
        ]);

        $family = Family::query()->create([
            'institution_id' => $institution->id,
            'administrator_id' => $admin->id,
            'data_entry_id' => $entryA->id,
            'head_name' => 'Head',
            'head_national_id' => '12345',
            'family_members_count' => 1,
        ]);

        Storage::disk('local')->put('family_attachments/file.txt', 'test');
        $attachment = FamilyAttachment::query()->create([
            'family_id' => $family->id,
            'uploaded_by' => $entryA->id,
            'name' => 'file.txt',
            'file_path' => 'family_attachments/file.txt',
            'mime_type' => 'text/plain',
            'size' => 4,
        ]);

        $this->actingAs($entryB)
            ->get(route('families.attachments.download', ['family' => $family->id, 'attachment' => $attachment->id]))
            ->assertForbidden();
    }
}
