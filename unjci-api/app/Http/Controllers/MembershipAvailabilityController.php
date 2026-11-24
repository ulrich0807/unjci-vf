<?php

namespace App\Http\Controllers;

use App\Services\MembershipAvailability;
use Illuminate\Http\Request;

class MembershipAvailabilityController extends Controller
{
    public function show(MembershipAvailability $availability)
    {
        return response()->json($availability->status())->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, MembershipAvailability $availability)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $validated = $request->validate(['requests_closed' => ['required', 'boolean']]);

        return response()->json($availability->setClosed((bool) $validated['requests_closed']))
            ->header('Cache-Control', 'no-store');
    }
}
