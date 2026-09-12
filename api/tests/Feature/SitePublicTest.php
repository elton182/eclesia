<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Igreja;
use App\Models\SiteFormSubmission;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SitePublicTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'platform-site@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Org Site',
            'slug' => 'org-site',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'org-site')->firstOrFail();
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
        $admin = User::findByEmail('admin@org-site.local');
        tenancy()->end();

        Sanctum::actingAs($admin);

        return $this->withHeader('X-Tenant', 'org-site')->json($method, $uri, $data);
    }

    private function publicGet(string $uri)
    {
        return $this->withHeader('X-Tenant', 'org-site')->getJson($uri);
    }

    public function test_public_site_returns_404_when_unpublished(): void
    {
        $this->publicGet('/api/v1/public/site')->assertNotFound();
    }

    public function test_admin_can_publish_settings_and_public_home(): void
    {
        $this->asAdminTenant('PUT', '/api/v1/site/settings', [
            'publicado' => true,
            'titulo' => 'Paróquias Unidas',
            'subtitulo' => 'Bem-vindos',
            'seo' => ['description' => 'Site da organização'],
            'menu' => [['label' => 'Início', 'slug' => 'home']],
        ])->assertOk()
            ->assertJsonPath('data.publicado', true)
            ->assertJsonPath('data.titulo', 'Paróquias Unidas');

        $page = $this->asAdminTenant('POST', '/api/v1/site/pages', [
            'slug' => 'home',
            'titulo' => 'Início',
            'status' => 'publicado',
            'is_home' => true,
            'blocks' => [
                [
                    'tipo' => 'hero',
                    'ordem' => 0,
                    'visivel' => true,
                    'payload' => [
                        'headline' => 'Bem-vindo',
                        'texto' => 'Nossa comunidade',
                        'cta_label' => 'Contato',
                        'cta_href' => '#contato',
                    ],
                ],
            ],
        ])->assertCreated()
            ->json('data');

        $this->assertSame('home', $page['slug']);
        $this->assertCount(1, $page['blocks']);

        $this->publicGet('/api/v1/public/site')
            ->assertOk()
            ->assertJsonPath('data.settings.titulo', 'Paróquias Unidas')
            ->assertJsonPath('data.page.slug', 'home')
            ->assertJsonPath('data.page.blocks.0.tipo', 'hero');
    }

    public function test_public_page_comunicados_pastorais_igrejas_and_form(): void
    {
        $this->asAdminTenant('PUT', '/api/v1/site/settings', [
            'publicado' => true,
            'titulo' => 'Site',
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        $igreja = Igreja::query()->firstOrFail();
        $igreja->update([
            'slug' => 'matriz',
            'publicado_no_site' => true,
            'descricao_publica' => 'Igreja matriz',
            'horario_missas' => 'Domingo 10h',
        ]);
        tenancy()->end();

        $this->asAdminTenant('POST', '/api/v1/site/pages', [
            'slug' => 'sobre',
            'titulo' => 'Sobre',
            'status' => 'publicado',
            'blocks' => [
                ['tipo' => 'richtext', 'payload' => ['html' => '<p>Olá</p>']],
            ],
        ])->assertCreated();

        $com = $this->asAdminTenant('POST', '/api/v1/site/comunicados', [
            'titulo' => 'Aviso',
            'corpo' => 'Conteúdo do aviso',
            'status' => 'publicado',
            'destaque' => true,
            'igreja_id' => null,
        ])->assertCreated()->json('data');

        $this->asAdminTenant('POST', '/api/v1/site/pastorais', [
            'igreja_id' => $igreja->id,
            'nome' => 'Liturgia',
            'descricao_publica' => 'Pastoral litúrgica',
            'publicado_no_site' => true,
            'ativa' => true,
        ])->assertCreated();

        $form = $this->asAdminTenant('POST', '/api/v1/site/forms', [
            'nome' => 'Contato',
            'slug' => 'contato',
            'ativo' => true,
            'sucesso_mensagem' => 'Obrigado!',
            'fields' => [
                ['nome' => 'nome', 'label' => 'Nome', 'tipo' => 'text', 'obrigatorio' => true],
                ['nome' => 'email', 'label' => 'E-mail', 'tipo' => 'email', 'obrigatorio' => true],
            ],
        ])->assertCreated()->json('data');

        $this->publicGet('/api/v1/public/site/pages/sobre')
            ->assertOk()
            ->assertJsonPath('data.slug', 'sobre');

        $this->publicGet('/api/v1/public/site/comunicados')
            ->assertOk()
            ->assertJsonPath('data.0.id', $com['id']);

        $this->publicGet('/api/v1/public/site/pastorais')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Liturgia');

        $this->publicGet('/api/v1/public/site/igrejas')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'matriz');

        $this->publicGet('/api/v1/public/site/igrejas/matriz')
            ->assertOk()
            ->assertJsonPath('data.horario_missas', 'Domingo 10h');

        $this->withHeader('X-Tenant', 'org-site')
            ->postJson('/api/v1/public/site/forms/contato/submissions', [
                'values' => ['nome' => 'Maria', 'email' => 'maria@example.com'],
                'website' => '',
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant', 'org-site')
            ->postJson('/api/v1/public/site/forms/contato/submissions', [
                'values' => ['nome' => 'Bot', 'email' => 'bot@example.com'],
                'website' => 'http://spam.test',
            ])
            ->assertUnprocessable();

        $subs = $this->asAdminTenant('GET', '/api/v1/site/forms/'.$form['id'].'/submissions')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $subs);
        $this->assertSame('Maria', $subs[0]['values']['nome']);

        tenancy()->initialize($this->tenant);
        $stored = SiteFormSubmission::query()->first();
        $this->assertNotNull($stored);
        $this->assertSame('Maria', $stored->payloadArray()['nome']);
        tenancy()->end();
    }

    public function test_draft_page_not_public(): void
    {
        $this->asAdminTenant('PUT', '/api/v1/site/settings', [
            'publicado' => true,
            'titulo' => 'Site',
        ])->assertOk();

        $this->asAdminTenant('POST', '/api/v1/site/pages', [
            'slug' => 'rascunho',
            'titulo' => 'Rascunho',
            'status' => 'rascunho',
            'blocks' => [],
        ])->assertCreated();

        $this->publicGet('/api/v1/public/site/pages/rascunho')->assertNotFound();
    }

    public function test_settings_requires_auth(): void
    {
        $this->withHeader('X-Tenant', 'org-site')
            ->getJson('/api/v1/site/settings')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_list_comunicados_and_settings(): void
    {
        Sanctum::actingAs($this->platform);

        $this->withHeader('X-Tenant', 'org-site')
            ->getJson('/api/v1/site/settings')
            ->assertOk()
            ->assertJsonMissingPath('data.cores');

        $this->withHeader('X-Tenant', 'org-site')
            ->getJson('/api/v1/site/comunicados')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->withHeader('X-Tenant', 'org-site')
            ->getJson('/api/v1/site/pastorais')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_settings_update_ignores_cores(): void
    {
        $this->asAdminTenant('PUT', '/api/v1/site/settings', [
            'publicado' => false,
            'titulo' => 'Sem cores',
            'cores' => ['primary' => '#ff0000'],
        ])->assertOk()
            ->assertJsonPath('data.titulo', 'Sem cores')
            ->assertJsonMissingPath('data.cores');
    }
}
