<?php

namespace App\Http\Controllers;

use App\Models\PressCompany;
use App\Models\PressMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AdminPressMediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $companies = PressCompany::query()
            ->with('media')
            ->orderBy('name')
            ->get()
            ->map(fn (PressCompany $company) => $this->companyData($company));

        return response()->json(['data' => $companies]);
    }

    public function storeCompany(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('press_companies', 'name')],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $company = PressCompany::create([
            'name' => $validated['name'],
            'is_active' => $validated['isActive'] ?? true,
        ]);
        $company->setRelation('media', collect());

        return response()->json(['data' => $this->companyData($company)], 201);
    }

    public function updateCompany(Request $request, PressCompany $company): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('press_companies', 'name')->ignore($company->id),
            ],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $values = ['name' => $validated['name']];
        if (array_key_exists('isActive', $validated)) {
            $values['is_active'] = $validated['isActive'];
        }

        $company->update($values);
        $company->load('media');

        return response()->json(['data' => $this->companyData($company)]);
    }

    public function destroyCompany(Request $request, PressCompany $company): Response|JsonResponse
    {
        $this->ensureAdmin($request);

        if ($company->media()->exists()) {
            return response()->json([
                'message' => 'Cette entreprise possède encore des médias. Supprimez-les ou réaffectez-les avant de supprimer l’entreprise.',
            ], 409);
        }

        $company->delete();

        return response()->noContent();
    }

    public function storeMedia(Request $request, PressCompany $company): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('press_media', 'name')
                    ->where(fn ($query) => $query->where('press_company_id', $company->id)),
            ],
            'type' => ['required', Rule::in(['Écrit', 'Numérique'])],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $media = $company->media()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'is_active' => $validated['isActive'] ?? true,
        ]);

        return response()->json(['data' => $this->mediaData($media)], 201);
    }

    public function updateMedia(Request $request, PressMedia $media): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'pressCompanyId' => ['required', 'integer', Rule::exists('press_companies', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('press_media', 'name')
                    ->where(fn ($query) => $query->where('press_company_id', $request->input('pressCompanyId')))
                    ->ignore($media->id),
            ],
            'type' => ['required', Rule::in(['Écrit', 'Numérique'])],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $values = [
            'press_company_id' => $validated['pressCompanyId'],
            'name' => $validated['name'],
            'type' => $validated['type'],
        ];
        if (array_key_exists('isActive', $validated)) {
            $values['is_active'] = $validated['isActive'];
        }

        $media->update($values);

        return response()->json(['data' => $this->mediaData($media)]);
    }

    public function destroyMedia(Request $request, PressMedia $media): Response
    {
        $this->ensureAdmin($request);
        $media->delete();

        return response()->noContent();
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Accès non autorisé.');
    }

    private function companyData(PressCompany $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'isActive' => $company->is_active,
            'media' => $company->media->map(fn (PressMedia $media) => $this->mediaData($media))->values(),
        ];
    }

    private function mediaData(PressMedia $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'type' => $media->type,
            'isActive' => $media->is_active,
        ];
    }
}
