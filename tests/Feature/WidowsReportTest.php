<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidowsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_can_filter_widows_by_marital_status(): void
    {
        $widowFemale = "\u{0627}\u{0631}\u{0645}\u{0644}\u{0629}"; // ارملة
        $marriedMale = "\u{0645}\u{062A}\u{0632}\u{0648}\u{062C}"; // متزوج

        $institution = Institution::query()->create(['name' => 'Inst']);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);

        $widowFamily = Family::query()->create([
            'institution_id' => $institution->id,
            'administrator_id' => $admin->id,
            'head_name' => 'Widow',
            'head_national_id' => '111',
            'marital_status' => $widowFemale,
            'family_members_count' => 3,
        ]);

        Family::query()->create([
            'institution_id' => $institution->id,
            'administrator_id' => $admin->id,
            'head_name' => 'Married',
            'head_national_id' => '222',
            'marital_status' => $marriedMale,
            'family_members_count' => 4,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.print', ['widow_status' => $widowFamily->marital_status]))
            ->assertOk()
            ->assertSee('Widow')
            ->assertDontSee('Married');
    }

    public function test_widows_export_downloads_csv(): void
    {
        $widowMale = "\u{0627}\u{0631}\u{0645}\u{0644}"; // ارمل

        $institution = Institution::query()->create(['name' => 'Inst']);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);

        Family::query()->create([
            'institution_id' => $institution->id,
            'administrator_id' => $admin->id,
            'head_name' => 'Widowed Man',
            'head_national_id' => '333',
            'marital_status' => $widowMale,
            'family_members_count' => 2,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('reports.widows.export'))
            ->assertOk()
            ->assertHeader('content-disposition');

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('widows_and_children_', $disposition);
    }
}
