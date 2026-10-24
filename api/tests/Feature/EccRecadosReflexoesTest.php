<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EccRecadosReflexoesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $igrejaId;

    private string $equipeAlphaId;

    private string $equipeBetaId;

    private User $admin;

    private User $liderAlpha;

    private User $liderBeta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $platform = SuperAdmin::query()->create([
            'name' => 'Platform',
            'email' => 'platform-recados@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Recados',
            'slug' => 'paroquia-recados',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'paroquia-recados')->firstOrFail();
        Auth::forgetGuards();

        tenancy()->initialize($this->tenant);
        $this->admin = User::findByEmail('admin@paroquia-recados.local');
        $this->igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        Sanctum::actingAs($this->admin);

        $this->equipeAlphaId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Alpha',
            'cor' => '#111111',
        ])->assertCreated()->json('data.id');

        $this->equipeBetaId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Beta',
            'cor' => '#222222',
        ])->assertCreated()->json('data.id');

        $liderAlphaId = $this->tenantJson('POST', '/api/v1/users', [
            'name' => 'Líder Alpha',
            'email' => 'lider-alpha@paroquia-recados.local',
            'password' => 'password123',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('PUT', "/api/v1/users/{$liderAlphaId}/roles", [
            'igreja_id' => $this->igrejaId,
            'roles' => [
                ['name' => 'lider-equipe', 'equipe_ids' => [$this->equipeAlphaId]],
            ],
        ])->assertOk();

        $liderBetaId = $this->tenantJson('POST', '/api/v1/users', [
            'name' => 'Líder Beta',
            'email' => 'lider-beta@paroquia-recados.local',
            'password' => 'password123',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('PUT', "/api/v1/users/{$liderBetaId}/roles", [
            'igreja_id' => $this->igrejaId,
            'roles' => [
                ['name' => 'lider-equipe', 'equipe_ids' => [$this->equipeBetaId]],
            ],
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        $this->liderAlpha = User::findByEmail('lider-alpha@paroquia-recados.local');
        $this->liderBeta = User::findByEmail('lider-beta@paroquia-recados.local');
        tenancy()->end();
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
     * @param  array<string, mixed>  $data
     */
    private function tenantJson(string $method, string $uri, array $data = [])
    {
        return $this->withHeader('X-Tenant', 'paroquia-recados')->json($method, $uri, $data);
    }

    private function asAdmin(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->admin);
    }

    private function asLiderAlpha(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->liderAlpha);
    }

    private function asLiderBeta(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->liderBeta);
    }

    public function test_recados_requer_auth(): void
    {
        Auth::forgetGuards();

        $this->withHeader('X-Tenant', 'paroquia-recados')
            ->getJson('/api/v1/ecc/recados')
            ->assertUnauthorized();
    }

    public function test_lider_nao_cria_recado(): void
    {
        $this->asLiderAlpha();

        $this->tenantJson('POST', '/api/v1/ecc/recados', [
            'titulo' => 'Aviso',
            'corpo' => 'Texto',
            'escopo' => 'paroquia',
            'status' => 'publicado',
        ])->assertForbidden();
    }

    public function test_admin_cria_e_lider_ve_recado_paroquia(): void
    {
        $this->asAdmin();

        $id = $this->tenantJson('POST', '/api/v1/ecc/recados', [
            'titulo' => 'Ensaio geral',
            'corpo' => 'Compareçam às 19h.',
            'urgente' => true,
            'fixado' => true,
            'escopo' => 'paroquia',
            'status' => 'publicado',
        ])->assertCreated()->json('data.id');

        $this->asLiderAlpha();
        $lista = $this->tenantJson('GET', '/api/v1/ecc/recados')->assertOk()->json('data');
        $this->assertCount(1, $lista);
        $this->assertSame($id, $lista[0]['id']);
        $this->assertTrue($lista[0]['urgente']);
        $this->assertTrue($lista[0]['fixado']);
    }

    public function test_recado_equipe_so_lider_da_equipe(): void
    {
        $this->asAdmin();

        $this->tenantJson('POST', '/api/v1/ecc/recados', [
            'titulo' => 'Só Alpha',
            'corpo' => 'Mensagem da secretaria',
            'escopo' => 'equipe',
            'equipe_id' => $this->equipeAlphaId,
            'status' => 'publicado',
        ])->assertCreated();

        $this->asLiderAlpha();
        $this->assertCount(1, $this->tenantJson('GET', '/api/v1/ecc/recados')->assertOk()->json('data'));

        $this->asLiderBeta();
        $this->assertCount(0, $this->tenantJson('GET', '/api/v1/ecc/recados')->assertOk()->json('data'));
    }

    public function test_recado_expirado_some_do_feed(): void
    {
        $this->asAdmin();

        $this->tenantJson('POST', '/api/v1/ecc/recados', [
            'titulo' => 'Expirado',
            'corpo' => 'Já passou',
            'escopo' => 'paroquia',
            'status' => 'publicado',
            'valido_ate' => now()->subDay()->toIso8601String(),
        ])->assertCreated();

        $this->asLiderAlpha();
        $this->assertCount(0, $this->tenantJson('GET', '/api/v1/ecc/recados')->assertOk()->json('data'));

        $this->asAdmin();
        $this->assertCount(1, $this->tenantJson('GET', '/api/v1/ecc/recados')->assertOk()->json('data'));
    }

    public function test_novidades_conta_desde(): void
    {
        $this->asAdmin();

        $this->tenantJson('POST', '/api/v1/ecc/recados', [
            'titulo' => 'Antigo',
            'corpo' => 'x',
            'escopo' => 'paroquia',
            'status' => 'publicado',
            'publicado_em' => now()->subDays(3)->toIso8601String(),
        ])->assertCreated();

        $this->tenantJson('POST', '/api/v1/ecc/recados', [
            'titulo' => 'Novo',
            'corpo' => 'y',
            'escopo' => 'paroquia',
            'status' => 'publicado',
            'publicado_em' => now()->subHour()->toIso8601String(),
        ])->assertCreated();

        $this->asLiderAlpha();
        $count = $this->tenantJson(
            'GET',
            '/api/v1/ecc/recados/novidades?desde='.urlencode(now()->subDay()->toIso8601String())
        )->assertOk()->json('data.count');

        $this->assertSame(1, $count);
    }

    public function test_reflexao_crud_e_lider_ve_publicada(): void
    {
        $this->asAdmin();

        $id = $this->withHeaders([
            'X-Tenant' => 'paroquia-recados',
            'Accept' => 'application/json',
        ])->post('/api/v1/ecc/reflexoes', [
            'titulo' => 'Sobre a misericórdia',
            'passagem_biblica' => 'Lc 15,1-7',
            'texto' => 'O Pai espera com os braços abertos.',
            'status' => 'publicado',
            'publicado_em' => '2026-10-20',
            'imagem' => UploadedFile::fake()->image('reflexao.jpg', 200, 200),
        ])->assertCreated()->json('data.id');

        $this->asLiderAlpha();
        $lista = $this->tenantJson('GET', '/api/v1/ecc/reflexoes')->assertOk()->json('data');
        $this->assertCount(1, $lista);
        $this->assertSame('Sobre a misericórdia', $lista[0]['titulo']);
        $this->assertSame('Lc 15,1-7', $lista[0]['passagem_biblica']);
        $this->assertTrue($lista[0]['tem_imagem']);

        $this->withHeader('X-Tenant', 'paroquia-recados')
            ->get("/api/v1/ecc/reflexoes/{$id}/imagem")
            ->assertOk();

        $this->tenantJson('POST', '/api/v1/ecc/reflexoes', [
            'titulo' => 'Hack',
            'texto' => 'x',
            'status' => 'publicado',
        ])->assertForbidden();
    }

    public function test_lider_nao_ve_rascunho_de_reflexao(): void
    {
        $this->asAdmin();

        $this->tenantJson('POST', '/api/v1/ecc/reflexoes', [
            'titulo' => 'Rascunho',
            'texto' => 'Ainda não',
            'status' => 'rascunho',
        ])->assertCreated();

        $this->asLiderAlpha();
        $this->assertCount(0, $this->tenantJson('GET', '/api/v1/ecc/reflexoes')->assertOk()->json('data'));
    }

    public function test_admin_remove_recado(): void
    {
        $this->asAdmin();
        $id = $this->tenantJson('POST', '/api/v1/ecc/recados', [
            'titulo' => 'Temp',
            'corpo' => 'x',
            'escopo' => 'paroquia',
            'status' => 'publicado',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('DELETE', "/api/v1/ecc/recados/{$id}")->assertNoContent();

        $this->asLiderAlpha();
        $this->assertCount(0, $this->tenantJson('GET', '/api/v1/ecc/recados')->assertOk()->json('data'));
    }
}
