<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SitePublicHtmlTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $platform;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => 'http://front.test',
            'app.url' => 'http://api.test',
        ]);

        $this->platform = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'platform-site-html@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Org Html',
            'slug' => 'org-html',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'org-html')->firstOrFail();
        Auth::forgetGuards();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $dbPath = database_path('tenant'.$this->tenant->id);
        if (File::exists($dbPath)) {
            File::delete($dbPath);
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function asAdminTenant(string $method, string $uri, array $data = [])
    {
        tenancy()->initialize($this->tenant);
        $admin = User::findByEmail('admin@org-html.local');
        tenancy()->end();

        Sanctum::actingAs($admin);

        return $this->withHeader('X-Tenant', 'org-html')->json($method, $uri, $data);
    }

    private function publishHomeWithBlocks(): void
    {
        $this->asAdminTenant('PUT', '/api/v1/site/settings', [
            'publicado' => true,
            'seo' => [
                'title' => 'Paróquia São José',
                'description' => 'Comunidade acolhedora no centro',
            ],
        ])->assertOk();

        $this->asAdminTenant('POST', '/api/v1/site/pages', [
            'slug' => 'home',
            'titulo' => 'Início',
            'status' => 'publicado',
            'is_home' => true,
            'seo' => [
                'title' => 'Home São José',
                'description' => 'Bem-vindos à nossa casa',
            ],
            'blocks' => [
                [
                    'tipo' => 'hero',
                    'ordem' => 0,
                    'visivel' => true,
                    'payload' => [
                        'headline' => 'Bem-vindo à Matriz',
                        'texto' => 'Nossa comunidade de fé',
                    ],
                ],
                [
                    'tipo' => 'html',
                    'ordem' => 1,
                    'visivel' => true,
                    'payload' => [
                        'html' => '<p>Texto seguro</p><script>alert(1)</script>',
                    ],
                ],
            ],
        ])->assertCreated();
    }

    public function test_html_home_returns_404_when_unpublished(): void
    {
        $this->get('/api/v1/public/site/html/org-html')
            ->assertNotFound();
    }

    public function test_html_home_includes_seo_and_block_text_without_script(): void
    {
        $this->publishHomeWithBlocks();

        $response = $this->get('/api/v1/public/site/html/org-html');
        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('<title>Home São José</title>', $html);
        $this->assertStringContainsString('name="description" content="Bem-vindos à nossa casa"', $html);
        $this->assertStringContainsString('property="og:title" content="Home São José"', $html);
        $this->assertStringContainsString('rel="canonical" href="http://front.test/site/org-html"', $html);
        $this->assertStringContainsString('rel="sitemap"', $html);
        $this->assertStringContainsString('Bem-vindo à Matriz', $html);
        $this->assertStringContainsString('Nossa comunidade de fé', $html);
        $this->assertStringContainsString('Texto seguro', $html);
        $this->assertStringContainsString('<h1>Org Html</h1>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringContainsString('id="app"', $html);
    }

    public function test_html_page_and_sitemap_exclude_drafts(): void
    {
        $this->publishHomeWithBlocks();

        $this->asAdminTenant('POST', '/api/v1/site/pages', [
            'slug' => 'sobre',
            'titulo' => 'Sobre nós',
            'status' => 'publicado',
            'blocks' => [
                [
                    'tipo' => 'richtext',
                    'payload' => ['html' => '<p>História da paróquia</p>'],
                ],
            ],
        ])->assertCreated();

        $this->asAdminTenant('POST', '/api/v1/site/pages', [
            'slug' => 'rascunho',
            'titulo' => 'Rascunho',
            'status' => 'rascunho',
            'blocks' => [
                ['tipo' => 'hero', 'payload' => ['headline' => 'Segredo']],
            ],
        ])->assertCreated();

        $pageHtml = $this->get('/api/v1/public/site/html/org-html/sobre')->assertOk()->getContent();
        $this->assertIsString($pageHtml);
        $this->assertStringContainsString('História da paróquia', $pageHtml);
        $this->assertStringContainsString('href="http://front.test/site/org-html/sobre"', $pageHtml);

        $this->get('/api/v1/public/site/html/org-html/rascunho')->assertNotFound();

        $sitemap = $this->get('/api/v1/public/site/sitemap/org-html')->assertOk()->getContent();
        $this->assertIsString($sitemap);
        $this->assertStringContainsString('application/xml', (string) $this->get('/api/v1/public/site/sitemap/org-html')->headers->get('Content-Type'));
        $this->assertStringContainsString('<loc>http://front.test/site/org-html</loc>', $sitemap);
        $this->assertStringContainsString('<loc>http://front.test/site/org-html/sobre</loc>', $sitemap);
        $this->assertStringNotContainsString('rascunho', $sitemap);
        $this->assertStringNotContainsString('Segredo', $sitemap);
    }

    public function test_html_unknown_tenant_and_path_without_header(): void
    {
        $this->publishHomeWithBlocks();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // Sem X-Tenant: tenancy vem do path.
        $html = $this->get('/api/v1/public/site/html/org-html')->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('Bem-vindo à Matriz', $html);

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->get('/api/v1/public/site/html/tenant-inexistente')
            ->assertStatus(400);
    }
}
