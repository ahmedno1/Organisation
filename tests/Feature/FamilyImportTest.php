<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FamilyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_families_from_csv_content(): void
    {
        $admin = $this->createAdmin();
        $file = UploadedFile::fake()->createWithContent(
            'families.csv',
            "head_name,head_national_id\nAli Test,123456789"
        );

        $response = $this->actingAs($admin)->post(route('reports.families.import'), [
            'file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        $this->assertDatabaseCount('families', 1);
        $this->assertSame('Ali Test', Family::query()->first()?->head_name);
    }

    public function test_import_accepts_non_csv_extension_if_content_is_csv(): void
    {
        $admin = $this->createAdmin();
        $file = UploadedFile::fake()->createWithContent(
            'families.any',
            "head_name,head_national_id\nSara Test,987654321"
        );

        $response = $this->actingAs($admin)->post(route('reports.families.import'), [
            'file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        $this->assertDatabaseCount('families', 1);
        $this->assertSame('Sara Test', Family::query()->first()?->head_name);
    }

    public function test_import_supports_arabic_export_headers_with_semicolon_delimiter(): void
    {
        $admin = $this->createAdmin();
        $file = UploadedFile::fake()->createWithContent(
            'families-export.txt',
            "اسم رب الأسرة;هوية رب الأسرة;المحافظة;رقم الجوال\nمحمد;111222333;غزة;0599999999"
        );

        $response = $this->actingAs($admin)->post(route('reports.families.import'), [
            'file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        $this->assertDatabaseCount('families', 1);
        $family = Family::query()->first();
        $this->assertSame('محمد', $family?->head_name);
        $this->assertSame('111222333', $family?->head_national_id);
        $this->assertSame('غزة', $family?->governorate);
    }

    public function test_import_detects_header_after_intro_lines_and_arabic_comma_delimiter(): void
    {
        $admin = $this->createAdmin();
        $file = UploadedFile::fake()->createWithContent(
            'families-weird.txt',
            "تقرير بيانات الأسر\nتم التصدير من النظام\nاسم رب الأسرة،هوية رب الأسرة،المحافظة\nأحمد علي،333222111،خانيونس"
        );

        $response = $this->actingAs($admin)->post(route('reports.families.import'), [
            'file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        $this->assertDatabaseCount('families', 1);
        $family = Family::query()->first();
        $this->assertSame('أحمد علي', $family?->head_name);
        $this->assertSame('333222111', $family?->head_national_id);
    }

    public function test_import_without_header_starts_from_second_row(): void
    {
        $admin = $this->createAdmin();
        $file = UploadedFile::fake()->createWithContent(
            'families-no-header.txt',
            "خالد,777888999,غزة\nمنى,111000222,رفح"
        );

        $response = $this->actingAs($admin)->post(route('reports.families.import'), [
            'file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        $this->assertDatabaseCount('families', 1);
        $first = Family::query()->orderBy('id')->first();
        $this->assertSame('منى', $first?->head_name);
        $this->assertSame('111000222', $first?->head_national_id);
    }

    public function test_developer_import_can_use_selected_institution_when_file_has_no_institution(): void
    {
        $institution = Institution::query()->create(['name' => 'Dev Import Inst']);
        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $file = UploadedFile::fake()->createWithContent(
            'developer-no-institution.csv',
            "head_name,head_national_id\nYousef,444555666"
        );

        $response = $this->actingAs($developer)->post(route('reports.families.import'), [
            'institution_id' => $institution->id,
            'file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('families', 1);
        $this->assertDatabaseHas('families', [
            'head_name' => 'Yousef',
            'head_national_id' => '444555666',
            'institution_id' => $institution->id,
        ]);
    }

    public function test_import_skips_duplicate_family_in_same_institution(): void
    {
        $admin = $this->createAdmin();
        Family::query()->create([
            'institution_id' => $admin->institution_id,
            'administrator_id' => $admin->id,
            'head_name' => 'Existing',
            'head_national_id' => '123123123',
            'family_members_count' => 1,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'families.csv',
            "head_name,head_national_id\nExisting Again,123123123"
        );

        $response = $this->actingAs($admin)->post(route('reports.families.import'), [
            'file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        $this->assertDatabaseCount('families', 1);
    }

    private function createAdmin(): User
    {
        $institution = Institution::query()->create([
            'name' => 'Import Institution',
        ]);

        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);
    }
}
