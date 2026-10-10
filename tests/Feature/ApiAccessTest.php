<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Family;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_valid_api_key(): void
    {
        $this->getJson('/api/v1/families')->assertUnauthorized();
        $this->getJson('/api/v1/families', ['X-API-KEY' => 'bad'])->assertUnauthorized();
    }

    public function test_api_returns_families_with_valid_api_key(): void
    {
        $institution = Institution::query()->create(['name' => 'Inst API']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'institution_id' => $institution->id]);
        $otherInstitution = Institution::query()->create(['name' => 'Other Inst']);
        $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN, 'institution_id' => $otherInstitution->id]);
        $plainTextKey = ApiKey::generatePlainText();
        ApiKey::query()->create([
            'institution_id' => $institution->id,
            'created_by' => $admin->id,
            'name' => 'Test key',
            'key_hash' => ApiKey::hashKey($plainTextKey),
            'permissions' => ['families.read', 'families.show'],
            'is_active' => true,
        ]);

        Family::query()->create([
            'institution_id' => $institution->id,
            'administrator_id' => $admin->id,
            'head_name' => 'API Head',
            'head_national_id' => '1001',
            'family_members_count' => 1,
        ]);
        Family::query()->create([
            'institution_id' => $otherInstitution->id,
            'administrator_id' => $otherAdmin->id,
            'head_name' => 'Other Institution Family',
            'head_national_id' => '2002',
            'family_members_count' => 1,
        ]);

        $this->getJson('/api/v1/families', ['X-API-KEY' => $plainTextKey])
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.head_name', 'API Head')
            ->assertJsonMissing(['head_name' => 'Other Institution Family']);
    }

    public function test_api_search_does_not_bypass_institution_scope(): void
    {
        $institution = Institution::query()->create(['name' => 'Inst API']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'institution_id' => $institution->id]);
        $otherInstitution = Institution::query()->create(['name' => 'Other Inst']);
        $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN, 'institution_id' => $otherInstitution->id]);

        $plainTextKey = ApiKey::generatePlainText();
        ApiKey::query()->create([
            'institution_id' => $institution->id,
            'created_by' => $admin->id,
            'name' => 'Scoped key',
            'key_hash' => ApiKey::hashKey($plainTextKey),
            'permissions' => ['families.read', 'families.show'],
            'is_active' => true,
        ]);

        Family::query()->create([
            'institution_id' => $institution->id,
            'administrator_id' => $admin->id,
            'head_name' => 'Searchable Family',
            'head_national_id' => '1001',
            'family_members_count' => 1,
        ]);

        Family::query()->create([
            'institution_id' => $otherInstitution->id,
            'administrator_id' => $otherAdmin->id,
            'head_name' => 'Searchable Family',
            'head_national_id' => '9009',
            'family_members_count' => 1,
        ]);

        $this->getJson('/api/v1/families?q=Searchable', ['X-API-KEY' => $plainTextKey])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.head_national_id', '1001');
    }
}
