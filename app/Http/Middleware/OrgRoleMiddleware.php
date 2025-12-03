<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OrgRoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('org.role:hr_manager')
     */
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();

        // Determine organization id - reading from request body/header/query param depends on your API
        // I recommend passing organization_id either in query param, header, or in route parameter
        $organizationId = $request->header('X-Organization-Id') ?: $request->input('organization_id') ?: $request->route('organization_id');

        if (!$organizationId) {
            return response()->json(['message' => 'organization_id missing'], 400);
        }

        if (!$user || !$user->hasRoleForOrganization($role, (int)$organizationId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
