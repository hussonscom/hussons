<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class TeamController extends Controller
{
    private const ROLE_RANKS = [
        'viewer' => 10,
        'auditor' => 20,
        'cashier' => 40,
        'warehouse' => 40,
        'pharmacist' => 40,
        'kitchen' => 40,
        'branch_manager' => 60,
        'admin' => 80,
        'owner' => 100,
    ];

    public function index(Request $request): View
    {
        [$organization, $actor] = $this->context($request);

        $memberships = Membership::query()
            ->where('organization_id', $organization->getKey())
            ->with(['user', 'branch'])
            ->orderByDesc('is_active')
            ->orderByRaw("case role when 'owner' then 1 when 'admin' then 2 when 'branch_manager' then 3 else 4 end")
            ->orderBy('id')
            ->get();

        $branches = Branch::query()
            ->where('organization_id', $organization->getKey())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $pendingInvitations = TeamInvitation::query()
            ->where('organization_id', $organization->getKey())
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->with('branch')
            ->latest()
            ->get();

        return view('team.index', [
            'organization' => $organization,
            'memberships' => $memberships,
            'branches' => $branches,
            'assignableRoles' => $this->assignableRoles($actor),
            'actorMembership' => $actor,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }

    public function invite(Request $request, AuditLogger $audit): RedirectResponse
    {
        [$organization, $actor] = $this->context($request);
        $roles = $this->assignableRoles($actor);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::in($roles)],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where('organization_id', $organization->getKey())->where('is_active', true),
            ],
        ]);

        if (User::query()->whereRaw('lower(email) = ?', [$validated['email']])->exists()) {
            throw ValidationException::withMessages(['email' => __('invitations.use_existing')]);
        }

        if (TeamInvitation::query()
            ->where('organization_id', $organization->getKey())
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->count() >= 100) {
            throw ValidationException::withMessages(['email' => __('invitations.too_many')]);
        }

        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($organization, $request, $validated, $token): TeamInvitation {
            TeamInvitation::query()
                ->where('organization_id', $organization->getKey())
                ->where('email', $validated['email'])
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return TeamInvitation::query()->create([
                'organization_id' => $organization->getKey(),
                'branch_id' => $validated['branch_id'] ?? null,
                'invited_by' => $request->user()->getKey(),
                'email' => $validated['email'],
                'role' => $validated['role'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(72),
            ]);
        });

        $audit->record('team.invitation_created', $invitation, null, [
            'email' => $invitation->email,
            'role' => $invitation->role,
            'branch_id' => $invitation->branch_id,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);

        return back()->with([
            'success' => __('invitations.created'),
            'invitation_url' => url('/invitation/'.$token),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        [$organization, $actor] = $this->context($request);
        $roles = $this->assignableRoles($actor);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::exists('users', 'email')->where('is_active', true)],
            'role' => ['required', Rule::in($roles)],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where('organization_id', $organization->getKey())->where('is_active', true),
            ],
        ]);

        $user = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower($validated['email'])])
            ->firstOrFail();
        if (Membership::query()->where('organization_id', $organization->getKey())->where('user_id', $user->getKey())->exists()) {
            throw ValidationException::withMessages(['email' => __('admin.already_member')]);
        }

        $membership = Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'branch_id' => $validated['branch_id'] ?? null,
            'role' => $validated['role'],
            'permissions' => null,
            'is_active' => true,
        ]);

        $audit->record('team.member_added', $membership, null, $membership->load('user', 'branch')->toArray());

        return back()->with('success', __('admin.member_added'));
    }

    public function update(Request $request, Membership $membership, AuditLogger $audit): RedirectResponse
    {
        [$organization, $actor] = $this->context($request);
        abort_unless($membership->organization_id === $organization->getKey(), 404);

        if ($membership->role === 'owner' && $actor->role !== 'owner') {
            abort(403);
        }

        $roles = $this->assignableRoles($actor);
        $validated = $request->validate([
            'role' => ['required', Rule::in($roles)],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where('organization_id', $organization->getKey())->where('is_active', true),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $validated['is_active'];
        if ($membership->user_id === $request->user()->getKey() && ! $isActive) {
            throw ValidationException::withMessages(['is_active' => __('admin.cannot_deactivate_self')]);
        }

        [$before, $after] = DB::transaction(function () use ($membership, $organization, $actor, $validated, $isActive): array {
            $locked = Membership::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($membership->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->role === 'owner' && $actor->role !== 'owner') {
                abort(403);
            }

            $removesOwner = $locked->role === 'owner'
                && ($validated['role'] !== 'owner' || ! $isActive);
            if ($removesOwner) {
                $activeOwnerCount = Membership::query()
                    ->where('organization_id', $organization->getKey())
                    ->where('role', 'owner')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get()
                    ->count();

                if ($activeOwnerCount <= 1) {
                    throw ValidationException::withMessages(['role' => __('admin.last_owner')]);
                }
            }

            $before = $locked->load('user', 'branch')->toArray();
            $locked->update([
                'role' => $validated['role'],
                'branch_id' => $validated['branch_id'] ?? null,
                'is_active' => $isActive,
            ]);

            return [$before, $locked->fresh()->load('user', 'branch')->toArray()];
        });
        $audit->record('team.member_updated', $membership, $before, $after);

        return back()->with('success', __('admin.member_updated'));
    }

    /** @return array{Organization, Membership} */
    private function context(Request $request): array
    {
        $organization = $request->attributes->get('organization');
        $membership = $request->attributes->get('membership');
        abort_unless($organization instanceof Organization && $membership instanceof Membership, 403);
        abort_unless(in_array($membership->role, ['owner', 'admin'], true), 403);

        return [$organization, $membership];
    }

    /** @return list<string> */
    private function assignableRoles(Membership $actor): array
    {
        $actorRank = self::ROLE_RANKS[$actor->role] ?? 0;

        return array_values(array_filter(
            array_keys(self::ROLE_RANKS),
            fn (string $role): bool => self::ROLE_RANKS[$role] <= $actorRank
                && ($role !== 'owner' || $actor->role === 'owner'),
        ));
    }
}
