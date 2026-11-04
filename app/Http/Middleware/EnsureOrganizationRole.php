<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $membership = $request->attributes->get('membership');

        abort_unless($membership instanceof Membership, 403);
        abort_unless(in_array($membership->role, $roles, true), 403);

        return $next($request);
    }
}
