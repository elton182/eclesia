<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByRequestDataException;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inicializa tenancy pelo slug no header X-Tenant (ADR-0002).
 */
class InitializeTenancyBySlug
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->header('X-Tenant') ?? $request->query('tenant');

        if (! is_string($slug) || $slug === '') {
            throw new TenantCouldNotBeIdentifiedByRequestDataException($slug ?? '');
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            throw new TenantCouldNotBeIdentifiedByRequestDataException($slug);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}
