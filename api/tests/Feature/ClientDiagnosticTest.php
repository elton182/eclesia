<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClientDiagnostic;
use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $lider;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'platform-diag@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Diag',
            'slug' => 'paroquia-diag',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'paroquia-diag')->firstOrFail();
        Auth::forgetGuards();

        $this->tenant->run(function (): void {
            $this->admin = User::findByEmail('admin@paroquia-diag.local');
            $this->assertNotNull($this->admin);

            $this->lider = User::query()->create([
                'name' => 'Lider Diag',
                'email' => 'lider-diag@paroquia-diag.local',
                'password' => 'password',
                'is_active' => true,
            ]);
            $igreja = Igreja::query()->first();
            setPermissionsTeamId($igreja?->id);
            $this->lider->assignRole('lider-equipe');
            setPermissionsTeamId(null);
        });
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        $this->deleteTenantDatabase($this->tenant->id);
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function samplePayload(): array
    {
        return [
            'motivo' => 'foto',
            'nota' => 'Galeria não abre',
            'meta' => [
                'app_version' => '1.0.0+abc',
                'standalone' => true,
                'camera_permission' => 'denied',
                'href' => '/ecc/casais',
                'online' => true,
            ],
            'events' => [
                ['t' => 1_700_000_000_000, 'type' => 'foto.camera.click', 'data' => ['blocked' => true]],
                ['t' => 1_700_000_000_100, 'type' => 'foto.gallery.click', 'data' => []],
            ],
        ];
    }

    public function test_usuario_autenticado_envia_diagnostico(): void
    {
        Sanctum::actingAs($this->lider);

        $this->withHeader('X-Tenant', 'paroquia-diag')
            ->postJson('/api/v1/client-diagnostics', $this->samplePayload())
            ->assertCreated()
            ->assertJsonPath('data.motivo', 'foto')
            ->assertJsonPath('data.meta.app_version', '1.0.0+abc')
            ->assertJsonPath('data.events_count', 2);

        $this->tenant->run(function (): void {
            $this->assertSame(1, ClientDiagnostic::query()->count());
            $row = ClientDiagnostic::query()->first();
            $this->assertSame($this->lider->id, $row->user_id);
            $this->assertSame('denied', $row->meta['camera_permission']);
        });
    }

    public function test_envio_sem_auth_retorna_401(): void
    {
        $this->withHeader('X-Tenant', 'paroquia-diag')
            ->postJson('/api/v1/client-diagnostics', $this->samplePayload())
            ->assertUnauthorized();
    }

    public function test_lider_nao_lista_diagnosticos(): void
    {
        Sanctum::actingAs($this->lider);

        $this->withHeader('X-Tenant', 'paroquia-diag')
            ->getJson('/api/v1/client-diagnostics')
            ->assertForbidden();
    }

    public function test_admin_lista_e_abre_detalhe(): void
    {
        Sanctum::actingAs($this->lider);
        $created = $this->withHeader('X-Tenant', 'paroquia-diag')
            ->postJson('/api/v1/client-diagnostics', $this->samplePayload())
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($this->admin);

        $this->withHeader('X-Tenant', 'paroquia-diag')
            ->getJson('/api/v1/client-diagnostics')
            ->assertOk()
            ->assertJsonPath('data.0.id', $created);

        $this->withHeader('X-Tenant', 'paroquia-diag')
            ->getJson('/api/v1/client-diagnostics/'.$created)
            ->assertOk()
            ->assertJsonPath('data.motivo', 'foto')
            ->assertJsonPath('data.events.0.type', 'foto.camera.click');
    }

    public function test_validacao_events_obrigatorios(): void
    {
        Sanctum::actingAs($this->admin);

        $this->withHeader('X-Tenant', 'paroquia-diag')
            ->postJson('/api/v1/client-diagnostics', [
                'motivo' => 'foto',
                'meta' => ['app_version' => '1'],
            ])
            ->assertStatus(422);
    }
}
