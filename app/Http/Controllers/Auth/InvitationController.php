<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class InvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->findInvitation($token);

        return view('auth.invitation', compact('invitation', 'token'));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
            'locale' => ['required', 'in:az,ru,en'],
        ]);

        [$user, $invitation] = DB::transaction(function () use ($token, $validated): array {
            $invitation = TeamInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invitation->isUsable()) {
                throw ValidationException::withMessages(['email' => __('invitations.unavailable')]);
            }

            if (User::query()->whereRaw('lower(email) = ?', [mb_strtolower($invitation->email)])->exists()) {
                throw ValidationException::withMessages(['email' => __('invitations.account_exists')]);
            }

            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => mb_strtolower($invitation->email),
                'password' => $validated['password'],
                'locale' => $validated['locale'],
                'timezone' => 'Asia/Baku',
                'is_active' => true,
            ]);

            Membership::query()->create([
                'organization_id' => $invitation->organization_id,
                'user_id' => $user->id,
                'branch_id' => $invitation->branch_id,
                'role' => $invitation->role,
                'permissions' => null,
                'is_active' => true,
            ]);

            $invitation->update([
                'accepted_by' => $user->id,
                'accepted_at' => now(),
            ]);

            AuditLog::query()->create([
                'organization_id' => $invitation->organization_id,
                'branch_id' => $invitation->branch_id,
                'actor_id' => $user->id,
                'action' => 'team.invitation_accepted',
                'auditable_type' => TeamInvitation::class,
                'auditable_id' => $invitation->id,
                'after' => ['email' => $invitation->email, 'role' => $invitation->role],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return [$user, $invitation];
        }, 3);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put([
            'organization_id' => $invitation->organization_id,
            'branch_id' => $invitation->branch_id,
            'locale' => $validated['locale'],
        ]);

        return redirect()->route('dashboard')->with('success', __('invitations.accepted'));
    }

    private function findInvitation(string $token): TeamInvitation
    {
        abort_unless(strlen($token) >= 40 && strlen($token) <= 128, 404);

        $invitation = TeamInvitation::query()
            ->with(['organization', 'branch'])
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        abort_unless($invitation->isUsable(), 410);

        return $invitation;
    }
}
