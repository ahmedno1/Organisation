<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FamilyResource;
use App\Models\ApiKey;
use App\Models\Family;
use Illuminate\Http\Request;

class FamilyApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Family::query()->with(['institution', 'members'])->latest();
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey?->institution_id) {
            $query->where('institution_id', $apiKey->institution_id);
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($builder) use ($q): void {
                $builder->where('head_name', 'like', "%{$q}%")
                    ->orWhere('head_national_id', 'like', "%{$q}%");
            });
        }

        if (! $apiKey?->institution_id && $request->filled('institution_id')) {
            $query->where('institution_id', $request->input('institution_id'));
        }

        return FamilyResource::collection($query->paginate(25));
    }

    public function show(Family $family): FamilyResource
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = request()->attributes->get('api_key');
        if ($apiKey?->institution_id && (int) $family->institution_id !== (int) $apiKey->institution_id) {
            abort(404);
        }

        $family->load(['institution', 'members']);

        return new FamilyResource($family);
    }
}
