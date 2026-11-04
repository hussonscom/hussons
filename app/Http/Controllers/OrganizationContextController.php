<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $membership = Membership::query()
            ->where('user_id', $request->user()->getKey())
            ->where('organization_id', $validated['organization_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $branchId = $validated['branch_id'] ?? $membership->branch_id;

        if ($membership->branch_id !== null && (int) $branchId !== (int) $membership->branch_id) {
            abort(403);
        }

        if ($branchId !== null) {
            Branch::query()
                ->whereKey($branchId)
                ->where('organization_id', $membership->organization_id)
                ->where('is_active', true)
                ->firstOrFail();
        }

        $request->session()->put([
            'organization_id' => $membership->organization_id,
            'branch_id' => $branchId,
        ]);

        return redirect()->route('dashboard');
    }
}
