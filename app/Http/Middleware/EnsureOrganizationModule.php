<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureOrganizationModule
{
    public function handle(Request $request, Closure $next, string ...$requiredModules): Response
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        $enabled = $this->normalizeModules($organization->modules);
        if ($enabled !== [] && ! $this->matches($enabled, $requiredModules)) {
            abort(404);
        }

        return $next($request);
    }

    /** @return list<string> */
    private function normalizeModules(mixed $modules): array
    {
        if (! is_array($modules)) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (mixed $module): string => strtolower(trim((string) $module)),
            array_filter($modules, static fn (mixed $module): bool => is_string($module) && trim($module) !== ''),
        )));
    }

    /** @param list<string> $enabled @param list<string> $required */
    private function matches(array $enabled, array $required): bool
    {
        foreach ($required as $module) {
            $aliases = match ($module) {
                'restaurant' => ['restaurant', 'cafe', 'food'],
                'retail' => ['retail', 'electronics'],
                default => [$module],
            };

            if (array_intersect($enabled, $aliases) !== []) {
                return true;
            }
        }

        return false;
    }
}
