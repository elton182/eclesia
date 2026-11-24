<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;

/**
 * Cache sem tags do CacheTenancyBootstrapper (stancl).
 *
 * CACHE_STORE=database|file não suportam tags(); o bootstrapper aplica tags em
 * toda chamada via Cache:: — o que quebra produção. Isolamento multi-tenant
 * já vem do DB por tenant (database) ou do path sufixado (file).
 *
 * Uso: UntaggedCache::store()->get/put/remember/...
 */
final class UntaggedCache
{
    public static function store(?string $name = null): Repository
    {
        return app('cache')->store($name ?? (string) config('cache.default'));
    }
}
