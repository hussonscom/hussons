<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterOrganizationRequest;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredOrganizationController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterOrganizationRequest $request): RedirectResponse
    {
        $rateKey = 'organization-registration:'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            throw ValidationException::withMessages([
                'email' => __('auth.registration_throttle'),
            ]);
        }

        RateLimiter::hit($rateKey, 3600);
        $data = $request->validated();

        [$user, $organization, $branch] = DB::transaction(function () use ($data): array {
            $branchNames = ['az' => 'Əsas filial', 'ru' => 'Главный филиал', 'en' => 'Main branch'];
            $warehouseNames = ['az' => 'Əsas anbar', 'ru' => 'Главный склад', 'en' => 'Main warehouse'];

            $user = User::forceCreate([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'locale' => $data['locale'],
                'is_active' => true,
            ]);

            $baseSlug = Str::slug($data['organization_name']) ?: 'company';
            $slug = $baseSlug;
            while (Organization::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.Str::lower(Str::random(6));
            }

            $organization = Organization::forceCreate([
                'name' => $data['organization_name'],
                'slug' => $slug,
                'currency' => 'AZN',
                'locale' => $data['locale'],
                'timezone' => 'Asia/Baku',
                'modules' => $data['business_types'],
                'is_active' => true,
            ]);

            $branch = Branch::forceCreate([
                'organization_id' => $organization->getKey(),
                'name' => $branchNames[$data['locale']],
                'code' => 'MAIN',
                'is_active' => true,
            ]);

            Warehouse::forceCreate([
                'organization_id' => $organization->getKey(),
                'branch_id' => $branch->getKey(),
                'name' => $warehouseNames[$data['locale']],
                'code' => 'MAIN',
                'is_active' => true,
            ]);

            Membership::forceCreate([
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'branch_id' => null,
                'role' => 'owner',
                'permissions' => null,
                'is_active' => true,
            ]);

            return [$user, $organization, $branch];
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put([
            'organization_id' => $organization->getKey(),
            'branch_id' => $branch->getKey(),
            'locale' => $data['locale'],
        ]);

        return redirect()->route('dashboard');
    }
}
