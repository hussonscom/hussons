<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $activeOrganizationIds = Organization::query()
            ->where('is_active', true)
            ->select('id');

        $memberships = Membership::query()
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->whereIn('organization_id', $activeOrganizationIds);

        $membership = null;
        $requestedOrganizationId = $request->session()->get('organization_id');

        if ($requestedOrganizationId !== null) {
            $membership = (clone $memberships)
                ->where('organization_id', $requestedOrganizationId)
                ->first();
        }

        $membership ??= $memberships->orderBy('id')->first();
        abort_unless($membership, 403, 'No active organization is assigned to this account.');

        $organization = Organization::query()
            ->whereKey($membership->organization_id)
            ->where('is_active', true)
            ->firstOrFail();

        $branchId = $membership->branch_id ?? $request->session()->get('branch_id');
        $branchQuery = Branch::query()
            ->where('organization_id', $organization->getKey())
            ->where('is_active', true);

        $branch = $branchId !== null
            ? (clone $branchQuery)->whereKey($branchId)->first()
            : null;

        $branch ??= $branchQuery->orderBy('id')->first();

        if ($membership->branch_id !== null && (! $branch || $branch->getKey() !== $membership->branch_id)) {
            abort(403, 'The assigned branch is unavailable.');
        }

        $locale = $request->session()->get('locale')
            ?? $user->locale
            ?? $organization->locale
            ?? 'az';
        if (! in_array($locale, ['az', 'ru', 'en'], true)) {
            $locale = 'az';
        }

        $request->session()->put([
            'organization_id' => $organization->getKey(),
            'branch_id' => $branch?->getKey(),
            'locale' => $locale,
        ]);

        $request->attributes->set('organization', $organization);
        $request->attributes->set('branch', $branch);
        $request->attributes->set('membership', $membership);

        App::setLocale($locale);
        app()->instance('currentOrganization', $organization);
        app()->instance('currentBranch', $branch);
        app()->instance('currentMembership', $membership);

        View::share([
            'currentOrganization' => $organization,
            'currentBranch' => $branch,
            'currentMembership' => $membership,
        ]);

        return $next($request);
    }
}
