<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateFamilyValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_duplicate_head_national_id_in_same_institution(): void
    {
        $institution = Institution::query()->create(['name' => 'Institution A']);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);

        Family::query()->create([
            'institution_id' => $institution->id,
            'administrator_id' => $admin->id,
            'head_name' => 'Existing Family Name',
            'head_national_id' => '999111222',
            'family_members_count' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('families.store'), [
            'head_name' => 'New Family Head Name',
            'head_national_id' => '999111222',
            'head_gender' => 'male',
            'head_birth_date' => '1992-02-02',
            'marital_status' => 'single',
            'family_members_count' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('head_national_id');
        $this->assertDatabaseCount('families', 1);
    }

    public function test_store_rejects_head_national_id_when_already_registered_as_member(): void
    {
        $institution = Institution::query()->create(['name' => 'Institution A']);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);

        $family = Family::query()->create([
            'institution_id' => $institution->id,
            'administrator_id' => $admin->id,
            'head_name' => 'Existing Family Name',
            'head_national_id' => '111222333',
            'family_members_count' => 2,
        ]);

        FamilyMember::query()->create([
            'family_id' => $family->id,
            'name' => 'Existing Member',
            'national_id' => '999111222',
        ]);

        $response = $this->actingAs($admin)->post(route('families.store'), [
            'head_name' => 'New Family Head Name',
            'head_national_id' => '999111222',
            'head_gender' => 'male',
            'head_birth_date' => '1992-02-02',
            'marital_status' => 'single',
            'family_members_count' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('head_national_id');
    }
}
