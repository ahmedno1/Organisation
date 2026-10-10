<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FamilyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'head_name' => $this->head_name,
            'head_national_id' => $this->head_national_id,
            'head_gender' => $this->head_gender,
            'marital_status' => $this->marital_status,
            'governorate' => $this->governorate,
            'mobile' => $this->mobile,
            'family_members_count' => $this->family_members_count,
            'priority_score' => $this->priority_score,
            'institution' => $this->institution?->name,
            'benefits_from_institution' => $this->benefits_from_institution,
            'created_at' => $this->created_at,
            'members' => $this->members->map(fn ($m) => [
                'name' => $m->name,
                'relation' => $m->relation,
                'birth_date' => $m->birth_date,
            ]),
        ];
    }
}
