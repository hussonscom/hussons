<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantContext
{
    public function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');

        if (! $organization && app()->bound('currentOrganization')) {
            $organization = app('currentOrganization');
        }

        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages([
                'organization' => __('Select an organization before continuing.'),
            ]);
        }

        return $organization;
    }

    public function branch(Request $request): Branch
    {
        $branch = $request->attributes->get('branch');

        if (! $branch && app()->bound('currentBranch')) {
            $branch = app('currentBranch');
        }

        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages([
                'branch' => __('Select a branch before continuing.'),
            ]);
        }

        return $branch;
    }

    public function warehouse(Request $request, ?int $warehouseId = null): Warehouse
    {
        $organization = $this->organization($request);
        $branch = $this->branch($request);

        return Warehouse::query()
            ->where('organization_id', $organization->id)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->when($warehouseId, fn ($query) => $query->whereKey($warehouseId))
            ->orderBy('id')
            ->firstOrFail();
    }
}
