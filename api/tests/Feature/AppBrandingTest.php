<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppBrandingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $platform;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->platform = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'platform-branding@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Org Branding',
            'slug' => 'org-branding',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'org-branding')->firstOrFail();
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
        $admin = User::findByEmail('admin@org-branding.local');
        tenancy()->end();

        Sanctum::actingAs($admin);

        return $this->withHeader('X-Tenant', 'org-branding')->json($method, $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function asLimitedUser(string $method, string $uri, array $data = [])
    {
        tenancy()->initialize($this->tenant);
        $user = User::findByEmail('lider-branding@org-branding.local');
        if ($user === null) {
            $user = User::query()->create([
                'name' => 'Líder',
                'email' => 'lider-branding@org-branding.local',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
            $user->assignRole('lider-equipe');
        }
        tenancy()->end();

        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant', 'org-branding')->json($method, $uri, $data);
    }

    public function test_get_branding_requires_auth(): void
    {
        $this->withHeader('X-Tenant', 'org-branding')
            ->getJson('/api/v1/app/branding')
            ->assertUnauthorized();
    }

    public function test_admin_can_get_default_branding(): void
    {
        $this->asAdminTenant('GET', '/api/v1/app/branding')
            ->assertOk()
            ->assertJsonPath('data.logo_path', null)
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonPath('data.cores', null);
    }

    public function test_admin_can_update_cores(): void
    {
        $this->asAdminTenant('PATCH', '/api/v1/app/branding', [
            'cores' => [
                'primary' => '#112233',
                'secondary' => '#AABBCC',
                'text' => '#010101',
                'text_muted' => '#666666',
                'on_primary' => '#FFFFFF',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.cores.primary', '#112233')
            ->assertJsonPath('data.cores.text', '#010101')
            ->assertJsonPath('data.cores.on_primary', '#FFFFFF');
    }

    public function test_rejects_invalid_hex_and_extra_keys(): void
    {
        $this->asAdminTenant('PATCH', '/api/v1/app/branding', [
            'cores' => ['primary' => 'vermelho'],
        ])->assertStatus(422);

        $this->asAdminTenant('PATCH', '/api/v1/app/branding', [
            'cores' => [
                'primary' => '#112233',
                'accent' => '#FFEEDD',
            ],
        ])->assertStatus(422);
    }

    public function test_limited_user_cannot_update_but_can_get(): void
    {
        $this->asLimitedUser('GET', '/api/v1/app/branding')->assertOk();

        $this->asLimitedUser('PATCH', '/api/v1/app/branding', [
            'cores' => ['primary' => '#112233'],
        ])->assertForbidden();
    }

    public function test_upload_and_delete_logo(): void
    {
        tenancy()->initialize($this->tenant);
        $admin = User::findByEmail('admin@org-branding.local');
        tenancy()->end();
        Sanctum::actingAs($admin);

        $upload = $this->withHeader('X-Tenant', 'org-branding')
            ->post('/api/v1/app/branding/logo', [
                'file' => UploadedFile::fake()->image('logo.png', 120, 120),
            ])
            ->assertOk();

        $logoUrl = $upload->json('data.logo_url');
        $this->assertNotNull($logoUrl);
        $this->assertNotNull($upload->json('data.logo_path'));

        $this->get($logoUrl)->assertOk();

        $this->asAdminTenant('DELETE', '/api/v1/app/branding/logo')->assertNoContent();

        $this->asAdminTenant('GET', '/api/v1/app/branding')
            ->assertOk()
            ->assertJsonPath('data.logo_path', null)
            ->assertJsonPath('data.logo_url', null);
    }

    public function test_web_me_branding_independent_from_site_settings_db(): void
    {
        $this->asAdminTenant('PATCH', '/api/v1/app/branding', [
            'cores' => [
                'primary' => '#111111',
                'secondary' => '#222222',
                'text' => '#333333',
                'text_muted' => '#444444',
                'on_primary' => '#555555',
            ],
        ])->assertOk();

        // PUT legado com cores/titulo é ignorado (SPEC-011); app branding permanece.
        $this->asAdminTenant('PUT', '/api/v1/site/settings', [
            'publicado' => false,
            'titulo' => 'Site',
            'cores' => [
                'primary' => '#AAAAAA',
                'secondary' => '#BBBBBB',
                'accent' => '#CCCCCC',
            ],
        ])->assertOk()
            ->assertJsonPath('data.cores.primary', '#111111');

        tenancy()->initialize($this->tenant);
        $admin = User::findByEmail('admin@org-branding.local');
        $appCores = AppSetting::query()->first()?->cores;
        tenancy()->end();

        $this->assertSame('#111111', $appCores['primary'] ?? null);

        Sanctum::actingAs($admin);
        $this->withHeader('X-Tenant', 'org-branding')
            ->postJson('/api/v1/web/me')
            ->assertOk()
            ->assertJsonPath('branding.cores.primary', '#111111')
            ->assertJsonMissingPath('branding.cores.accent');
    }

    public function test_web_login_returns_app_branding(): void
    {
        $this->asAdminTenant('PATCH', '/api/v1/app/branding', [
            'cores' => [
                'primary' => '#ABCDEF',
                'secondary' => '#123456',
                'text' => '#111111',
                'text_muted' => '#222222',
                'on_primary' => '#FEDCBA',
            ],
        ])->assertOk();

        $this->postJson('/api/v1/web/login', [
            'tenant' => 'org-branding',
            'email' => 'admin@org-branding.local',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('branding.cores.primary', '#ABCDEF')
            ->assertJsonPath('branding.cores.on_primary', '#FEDCBA')
            ->assertJsonMissingPath('branding.cores.accent');
    }
}
