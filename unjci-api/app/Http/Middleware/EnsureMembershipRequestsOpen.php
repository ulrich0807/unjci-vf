<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMembershipRequestsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('membership.requests_closed')) {
            return response()->json(['message' => config('membership.closure_message')], 403);
        }

        return $next($request);
    }
}
