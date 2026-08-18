<?php

namespace App\Http\Controllers;

use App\Models\PressMedia;
use Illuminate\Http\JsonResponse;

class PressMediaController extends Controller
{
    public function index(): JsonResponse
    {
        $media = PressMedia::query()
            ->join('press_companies', 'press_companies.id', '=', 'press_media.press_company_id')
            ->where('press_companies.is_active', true)
            ->where('press_media.is_active', true)
            ->orderBy('press_companies.name')
            ->orderBy('press_media.name')
            ->get([
                'press_media.id',
                'press_media.press_company_id',
                'press_companies.name as company_name',
                'press_media.name',
                'press_media.type',
            ])
            ->map(fn (PressMedia $item) => [
                'id' => $item->id,
                'companyId' => $item->press_company_id,
                'company' => $item->company_name,
                'name' => $item->name,
                'type' => $item->type,
            ]);

        return response()->json(['data' => $media]);
    }
}
