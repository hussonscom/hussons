<?php

namespace Tests\Feature\Concerns;

use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Str;

trait CreatesWorkspace
{
    /**
     * @return array{user: User, organization: Organization, branch: Branch, warehouse: Warehouse, membership: Membership}
     */
    protected function createWorkspace(?User $user = null, string $role = 'owner'): array
    {
        $user ??= User::factory()->create(['is_active' => true, 'locale' => 'az']);
        $token = Str::lower(Str::random(8));

        $organization = Organization::query()->create([
            'name' => "Workspace {$token}",
            'slug' => "workspace-{$token}",
            'currency' => 'AZN',
            'locale' => 'az',
            'timezone' => 'Asia/Baku',
            'modules' => ['retail', 'electronics', 'pharmacy', 'restaurant'],
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Main branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Main warehouse',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $membership = Membership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'branch_id' => null,
            'role' => $role,
            'permissions' => ['*'],
            'is_active' => true,
        ]);

        return compact('user', 'organization', 'branch', 'warehouse', 'membership');
    }

    protected function createProduct(Organization $organization, array $overrides = []): Product
    {
        $token = Str::upper(Str::random(8));

        return Product::query()->create(array_merge([
            'organization_id' => $organization->id,
            'type' => 'standard',
            'sku' => "SKU-{$token}",
            'barcode' => null,
            'name_az' => "Product {$token}",
            'name_ru' => "Product {$token}",
            'name_en' => "Product {$token}",
            'unit' => 'pcs',
            'tracking_mode' => 'none',
            'min_stock' => 0,
            'purchase_price' => 50,
            'sale_price' => 80,
            'tax_rate' => 0,
            'is_active' => true,
            'metadata' => [],
        ], $overrides));
    }

    /** @param array{organization: Organization, branch: Branch} $workspace */
    protected function workspaceSession(array $workspace): array
    {
        return [
            'organization_id' => $workspace['organization']->id,
            'branch_id' => $workspace['branch']->id,
            'locale' => 'az',
        ];
    }
}
