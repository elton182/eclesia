<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TenantResolutionException;
use App\Models\Tenant;
use App\Models\TenantAlias;
use Illuminate\Support\Str;

class TenantResolver
{
    /**
     * Resolve tenant por slug, apelido ou nome (único, case-insensitive).
     *
     * @throws TenantResolutionException
     */
    public function resolve(string $identifier): Tenant
    {
        $raw = trim($identifier);
        if ($raw === '') {
            throw TenantResolutionException::notFound($raw);
        }

        $normalized = Str::lower($raw);

        $bySlug = Tenant::query()->where('slug', $normalized)->first();
        if ($bySlug !== null) {
            return $bySlug;
        }

        $alias = TenantAlias::query()->where('alias', $normalized)->first();
        if ($alias !== null) {
            $tenant = $alias->tenant;
            if ($tenant !== null) {
                return $tenant;
            }
        }

        $byName = Tenant::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->get();

        if ($byName->count() === 1) {
            return $byName->first();
        }

        if ($byName->count() > 1) {
            throw TenantResolutionException::ambiguous($raw);
        }

        throw TenantResolutionException::notFound($raw);
    }

    /**
     * @param  list<string>  $aliases
     * @return list<string>
     */
    public function normalizeAliases(array $aliases): array
    {
        $normalized = [];
        foreach ($aliases as $alias) {
            if (! is_string($alias)) {
                continue;
            }
            $value = Str::lower(trim($alias));
            if ($value === '') {
                continue;
            }
            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }
}
