<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SiteService;
use App\Support\UntaggedCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper;
use Stancl\Tenancy\CacheManager as TenantCacheManager;
use Tests\TestCase;

/**
 * Regressão: CACHE_STORE=database + CacheTenancyBootstrapper (tags) quebrava
 * criação de igreja via SiteService::forgetPublicSeoCache().
 */
class UntaggedCacheSeoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'platform-cache@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Org Cache',
            'slug' => 'org-cache',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'org-cache')->firstOrFail();
        Auth::forgetGuards();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->deleteTenantDatabase($this->tenant->id);

        parent::tearDown();
    }

    public function test_forget_public_seo_cache_works_with_database_store_under_tagged_tenancy_cache(): void
    {
        config(['cache.default' => 'database']);

        tenancy()->initialize($this->tenant);
        $this->wrapCacheWithTenantTags();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('This cache store does not support tagging.');
        Cache::get(SiteService::SEO_CACHE_VERSION_KEY);
    }

    public function test_untagged_cache_bumps_seo_version_when_tags_are_unsupported(): void
    {
        config(['cache.default' => 'database']);

        tenancy()->initialize($this->tenant);
        $this->wrapCacheWithTenantTags();

        try {
            Cache::get('probe');
            $this->fail('Cache facade should reject tagging on database store.');
        } catch (\BadMethodCallException $e) {
            $this->assertStringContainsString('does not support tagging', $e->getMessage());
        }

        app(SiteService::class)->forgetPublicSeoCache();

        $this->assertSame(
            1,
            (int) UntaggedCache::store()->get(SiteService::SEO_CACHE_VERSION_KEY, 0)
        );

        tenancy()->end();
    }

    public function test_create_igreja_succeeds_with_database_cache_and_cache_tenancy_bootstrapper(): void
    {
        config([
            'cache.default' => 'database',
            'tenancy.bootstrappers' => array_values(array_unique(array_merge(
                [CacheTenancyBootstrapper::class],
                config('tenancy.bootstrappers')
            ))),
        ]);

        tenancy()->initialize($this->tenant);
        $admin = User::findByEmail('admin@org-cache.local');
        tenancy()->end();

        Sanctum::actingAs($admin);

        $this->withHeader('X-Tenant', 'org-cache')
            ->postJson('/api/v1/igrejas', [
                'nome' => 'Comunidade Cache',
                'tipo' => 'comunidade',
                'cidade' => 'Campinas',
                'uf' => 'SP',
            ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Comunidade Cache');
    }

    /**
     * Simula CacheTenancyBootstrapper: toda chamada Cache::* usa tags().
     */
    private function wrapCacheWithTenantTags(): void
    {
        $app = app();
        $app->extend('cache', static fn () => new TenantCacheManager($app));
        Cache::clearResolvedInstances();
    }
}
