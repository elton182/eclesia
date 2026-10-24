<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EccInteracoesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $igrejaId;

    private User $admin;

    private User $lider;

    private string $recadoId;

    private string $reflexaoId;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = SuperAdmin::query()->create([
            'name' => 'Platform',
            'email' => 'platform-interacoes@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Interações',
            'slug' => 'paroquia-interacoes',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'paroquia-interacoes')->firstOrFail();
        Auth::forgetGuards();

        tenancy()->initialize($this->tenant);
        $this->admin = User::findByEmail('admin@paroquia-interacoes.local');
        $this->igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        Sanctum::actingAs($this->admin);

        $equipeId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Alpha',
            'cor' => '#111111',
        ])->assertCreated()->json('data.id');

        $liderId = $this->tenantJson('POST', '/api/v1/users', [
            'name' => 'Líder Alpha',
            'email' => 'lider-interacoes@paroquia-interacoes.local',
            'password' => 'password123',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('PUT', "/api/v1/users/{$liderId}/roles", [
            'igreja_id' => $this->igrejaId,
            'roles' => [
                ['name' => 'lider-equipe', 'equipe_ids' => [$equipeId]],
            ],
        ])->assertOk();

        $this->recadoId = $this->tenantJson('POST', '/api/v1/ecc/recados', [
            'titulo' => 'Aviso',
            'corpo' => 'Texto do recado',
            'escopo' => 'paroquia',
            'status' => 'publicado',
        ])->assertCreated()->json('data.id');

        $this->reflexaoId = $this->tenantJson('POST', '/api/v1/ecc/reflexoes', [
            'titulo' => 'Misericórdia',
            'texto' => 'Reflexão do diácono',
            'status' => 'publicado',
        ])->assertCreated()->json('data.id');

        tenancy()->initialize($this->tenant);
        $this->lider = User::findByEmail('lider-interacoes@paroquia-interacoes.local');
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
        return $this->withHeader('X-Tenant', 'paroquia-interacoes')->json($method, $uri, $data);
    }

    private function asLider(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->lider);
    }

    private function asAdmin(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->admin);
    }

    public function test_like_e_unlike_recado(): void
    {
        $this->asLider();

        $liked = $this->tenantJson('POST', "/api/v1/ecc/recados/{$this->recadoId}/like")
            ->assertOk()
            ->json('data');
        $this->assertTrue($liked['liked']);
        $this->assertSame(1, $liked['likes_count']);

        $lista = $this->tenantJson('GET', '/api/v1/ecc/recados')->assertOk()->json('data');
        $this->assertTrue($lista[0]['liked_by_me']);
        $this->assertSame(1, $lista[0]['likes_count']);

        $unliked = $this->tenantJson('DELETE', "/api/v1/ecc/recados/{$this->recadoId}/like")
            ->assertOk()
            ->json('data');
        $this->assertFalse($unliked['liked']);
        $this->assertSame(0, $unliked['likes_count']);
    }

    public function test_comentario_lider_fica_pendente_e_admin_aprova(): void
    {
        $this->asLider();

        $comentario = $this->tenantJson('POST', "/api/v1/ecc/recados/{$this->recadoId}/comentarios", [
            'corpo' => 'Amém, vamos participar!',
        ])->assertCreated()->json('data');

        $this->assertSame('pendente', $comentario['status']);

        $listaLider = $this->tenantJson('GET', "/api/v1/ecc/recados/{$this->recadoId}/comentarios")
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $listaLider);

        $this->asAdmin();
        // outro admin veria só aprovados no feed público — mas como manage vê todos
        $pendentes = $this->tenantJson('GET', '/api/v1/ecc/interacoes/comentarios?status=pendente')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $pendentes);

        $aprovado = $this->tenantJson(
            'POST',
            "/api/v1/ecc/interacoes/comentarios/{$comentario['id']}/aprovar"
        )->assertOk()->json('data');
        $this->assertSame('aprovado', $aprovado['status']);

        $this->asLider();
        $lista = $this->tenantJson('GET', "/api/v1/ecc/recados/{$this->recadoId}/comentarios")
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $lista);
        $this->assertSame('aprovado', $lista[0]['status']);
    }

    public function test_lider_nao_modera(): void
    {
        $this->asLider();
        $id = $this->tenantJson('POST', "/api/v1/ecc/reflexoes/{$this->reflexaoId}/comentarios", [
            'corpo' => 'Bela reflexão',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('POST', "/api/v1/ecc/interacoes/comentarios/{$id}/aprovar")
            ->assertForbidden();
    }

    public function test_comentario_admin_ja_aprovado(): void
    {
        $this->asAdmin();
        $c = $this->tenantJson('POST', "/api/v1/ecc/reflexoes/{$this->reflexaoId}/comentarios", [
            'corpo' => 'Publicado pela secretaria',
        ])->assertCreated()->json('data');

        $this->assertSame('aprovado', $c['status']);
    }

    public function test_autor_pode_excluir_comentario_proprio(): void
    {
        $this->asLider();
        $id = $this->tenantJson('POST', "/api/v1/ecc/recados/{$this->recadoId}/comentarios", [
            'corpo' => 'Vou apagar',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('DELETE', "/api/v1/ecc/interacoes/comentarios/{$id}")
            ->assertNoContent();
    }

    public function test_like_requer_auth(): void
    {
        Auth::forgetGuards();
        $this->withHeader('X-Tenant', 'paroquia-interacoes')
            ->postJson("/api/v1/ecc/recados/{$this->recadoId}/like")
            ->assertUnauthorized();
    }
}
