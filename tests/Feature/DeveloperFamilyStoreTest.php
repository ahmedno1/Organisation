<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperFamilyStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_store_family_without_administrator(): void
    {
        $developer = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
        ]);
        $institution = Institution::query()->create(['name' => 'Institution A']);

        $response = $this->actingAs($developer)->post(route('families.store'), [
            'institution_id' => $institution->id,
            'head_name' => 'Family Head Full Name',
            'head_national_id' => '123456789',
            'head_gender' => 'male',
            'head_birth_date' => '1990-01-01',
            'marital_status' => 'اعزب',
        ]);

        $response->assertRedirect(route('families.index'));
        $this->assertDatabaseHas('families', [
            'institution_id' => $institution->id,
            'administrator_id' => null,
            'head_national_id' => '123456789',
        ]);
    }

    public function test_developer_store_rejects_administrator_from_different_institution(): void
    {
        $developer = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
        ]);
        $selectedInstitution = Institution::query()->create(['name' => 'Institution A']);
        $otherInstitution = Institution::query()->create(['name' => 'Institution B']);
        $otherInstitutionAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'institution_id' => $otherInstitution->id,
        ]);

        $response = $this->actingAs($developer)->post(route('families.store'), [
            'institution_id' => $selectedInstitution->id,
            'administrator_id' => $otherInstitutionAdmin->id,
            'head_name' => 'Family Head Full Name',
            'head_national_id' => '987654321',
            'head_gender' => 'male',
            'head_birth_date' => '1990-01-01',
            'marital_status' => 'اعزب',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('administrator_id');
        $this->assertDatabaseMissing('families', [
            'head_national_id' => '987654321',
        ]);
    }
}
