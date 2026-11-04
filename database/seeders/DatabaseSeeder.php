<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(IndustryCategorySeeder::class);

        $email = trim((string) env('SEED_ADMIN_EMAIL'));
        $password = (string) env('SEED_ADMIN_PASSWORD');

        if ($email === '' || $password === '') {
            return;
        }

        DB::transaction(function () use ($email, $password): void {
            $admin = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => trim((string) env('SEED_ADMIN_NAME', 'Hussons Administrator')),
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'locale' => env('SEED_ADMIN_LOCALE', 'az'),
                    'timezone' => 'Asia/Baku',
                    'is_platform_admin' => true,
                    'is_active' => true,
                ],
            );

            $organizationName = trim((string) env('SEED_ORGANIZATION_NAME', 'Hussons One'));
            $organizationSlug = trim((string) env('SEED_ORGANIZATION_SLUG'));

            $organization = Organization::query()->updateOrCreate(
                ['slug' => $organizationSlug !== '' ? $organizationSlug : Str::slug($organizationName)],
                [
                    'name' => $organizationName,
                    'currency' => 'AZN',
                    'locale' => env('SEED_ADMIN_LOCALE', 'az'),
                    'timezone' => 'Asia/Baku',
                    'modules' => ['electronics', 'pharmacy', 'food', 'retail'],
                    'is_active' => true,
                ],
            );

            $branch = Branch::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => 'HQ',
                ],
                [
                    'name' => env('SEED_BRANCH_NAME', 'Main'),
                    'is_active' => true,
                ],
            );

            Warehouse::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => 'MAIN',
                ],
                [
                    'branch_id' => $branch->id,
                    'name' => env('SEED_WAREHOUSE_NAME', 'Main warehouse'),
                    'is_active' => true,
                ],
            );

            Membership::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $admin->id,
                ],
                [
                    'branch_id' => $branch->id,
                    'role' => 'owner',
                    'permissions' => null,
                    'is_active' => true,
                ],
            );
        });
    }
}
