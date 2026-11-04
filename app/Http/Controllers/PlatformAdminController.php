<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformAdminController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless((bool) $request->user()?->is_platform_admin, 403);

        $organizations = Organization::query()
            ->withCount(['branches', 'memberships', 'products', 'sales'])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->string('q'));
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%")
                        ->orWhere('tax_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'organizations' => Organization::query()->count(),
            'active' => Organization::query()->where('is_active', true)->count(),
            'branches' => Branch::query()->count(),
            'memberships' => Membership::query()->count(),
        ];

        return view('platform.index', compact('organizations', 'stats'));
    }
}
