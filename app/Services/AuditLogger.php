<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null): AuditLog
    {
        $request = request();
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $branch = app()->bound('currentBranch') ? app('currentBranch') : null;

        return AuditLog::query()->create([
            'organization_id' => $organization?->getKey(),
            'branch_id' => $branch?->getKey(),
            'actor_id' => $request->user()?->getKey(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'correlation_id' => (string) Str::uuid(),
        ]);
    }
}
