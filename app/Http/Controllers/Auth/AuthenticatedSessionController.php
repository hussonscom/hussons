<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();
        if (isset($user->is_active) && ! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => __('auth.disabled'),
            ]);
        }

        $membership = Membership::query()
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $membership) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => __('auth.no_organization'),
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();
        $request->session()->put([
            'organization_id' => $membership->organization_id,
            'branch_id' => $membership->branch_id,
            'locale' => in_array($user->locale ?? null, ['az', 'ru', 'en'], true)
                ? $user->locale
                : 'az',
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
