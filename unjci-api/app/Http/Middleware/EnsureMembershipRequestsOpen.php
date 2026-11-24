<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\MembershipAvailability;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMembershipRequestsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(MembershipAvailability::class)->isClosed()) {
            return response()->json([
                'code' => 'membership_closed',
                'message' => config('membership.closure_message'),
            ], 403)->header('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}
