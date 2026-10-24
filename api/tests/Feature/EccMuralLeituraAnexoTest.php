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

class EccMuralLeituraAnexoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $igrejaId;

    private string $equipeAlphaId;

    private User $admin;

    private User $liderAlpha;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $platform = SuperAdmin::query()->create([
            'name' => 'Platform',
            'email' => 'platform-mural@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Mural',
            'slug' => 'paroquia-mural',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'paroquia-mural')->firstOrFail();
        Auth::forgetGuards();

        tenancy()->initialize($this->tenant);
        $this->admin = User::findByEmail('admin@paroquia-mural.local');
        $this->igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        Sanctum::actingAs($this->admin);

        $this->equipeAlphaId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Alpha',
            'cor' => '#111111',
        ])->assertCreated()->json('data.id');

        $liderAlphaId = $this->tenantJson('POST', '/api/v1/users', [
            'name' => 'Líder Alpha',
            'email' => 'lider-alpha@paroquia-mural.local',
            'password' => 'password123',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('PUT', "/api/v1/users/{$liderAlphaId}/roles", [
            'igreja_id' => $this->igrejaId,
            'roles' => [
                ['name' => 'lider-equipe', 'equipe_ids' => [$this->equipeAlphaId]],
            ],
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        $this->liderAlpha = User::findByEmail('lider-alpha@paroquia-mural.local');
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
        return $this->withHeader('X-Tenant', 'paroquia-mural')->json($method, $uri, $data);
    }

    private function asAdmin(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->admin);
    }

    private function asLider(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->liderAlpha);
    }

    public function test_lista_retorna_resumo_sem_corpo_para_lider(): void
    {
        $this->asAdmin();
        $corpo = str_repeat('Palavra longa do comunicado. ', 20);
        $id = $this->tenantJson('POST', '/api/v1/ecc/recados', [
            'titulo' => 'Aviso longo',
            'corpo' => $corpo,
            'escopo' => 'paroquia',
            'status' => 'publicado',
        ])->assertCreated()->json('data.id');

        $this->asLider();
        $item = $this->tenantJson('GET', '/api/v1/ecc/recados')->assertOk()->json('data.0');
        $this->assertArrayHasKey('resumo', $item);
        $this->assertArrayNotHasKey('corpo', $item);
        $this->assertFalse($item['lido_por_mim']);
        $this->assertStringEndsWith('…', $item['resumo']);
        $this->assertSame(0, $item['likes_count']);
        $this->assertSame(0, $item['leituras_count']);

        $this->tenantJson('POST', "/api/v1/ecc/recados/{$id}/like")->assertOk();
        $this->tenantJson('POST', "/api/v1/ecc/recados/{$id}/ler")->assertOk();

        $item2 = $this->tenantJson('GET', '/api/v1/ecc/recados')->assertOk()->json('data.0');
        $this->assertSame(1, $item2['likes_count']);
        $this->assertSame(1, $item2['leituras_count']);
    }

    public function test_ler_diminui_nao_lidos_e_grava_lido_em(): void
    {
        $this->asAdmin();
        $id = $this->tenantJson('POST', '/api/v1/ecc/recados', [
            'titulo' => 'Novo',
            'corpo' => 'Corpo completo do recado',
            'escopo' => 'paroquia',
            'status' => 'publicado',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('POST', '/api/v1/ecc/reflexoes', [
            'titulo' => 'Reflexão',
            'texto' => 'Texto da reflexão completo',
            'status' => 'publicado',
        ])->assertCreated();

        $this->asLider();
        $naoLidos = $this->tenantJson('GET', '/api/v1/ecc/mural/nao-lidos')->assertOk()->json('data');
        $this->assertSame(1, $naoLidos['recados']);
        $this->assertSame(1, $naoLidos['reflexoes']);

        $ler = $this->tenantJson('POST', "/api/v1/ecc/recados/{$id}/ler")->assertOk()->json('data');
        $this->assertTrue($ler['lido_por_mim']);
        $this->assertNotEmpty($ler['lido_em']);

        $detail = $this->tenantJson('GET', "/api/v1/ecc/recados/{$id}")->assertOk()->json('data');
        $this->assertSame('Corpo completo do recado', $detail['corpo']);
        $this->assertTrue($detail['lido_por_mim']);

        $naoLidos2 = $this->tenantJson('GET', '/api/v1/ecc/mural/nao-lidos')->assertOk()->json('data');
        $this->assertSame(0, $naoLidos2['recados']);
        $this->assertSame(1, $naoLidos2['reflexoes']);
    }

    public function test_anexo_upload_e_download(): void
    {
        $this->asAdmin();
        $file = UploadedFile::fake()->create('aviso.pdf', 120, 'application/pdf');

        $id = $this->withHeaders([
            'X-Tenant' => 'paroquia-mural',
            'Accept' => 'application/json',
        ])->post('/api/v1/ecc/recados', [
            'titulo' => 'Com PDF',
            'corpo' => 'Veja o anexo',
            'escopo' => 'paroquia',
            'status' => 'publicado',
            'anexo' => $file,
        ])->assertCreated()->json('data.id');

        $created = $this->tenantJson('GET', "/api/v1/ecc/recados/{$id}")->assertOk()->json('data');
        $this->assertTrue($created['tem_anexo']);
        $this->assertSame('aviso.pdf', $created['anexo_nome']);

        $this->asLider();
        $this->withHeader('X-Tenant', 'paroquia-mural')
            ->get("/api/v1/ecc/recados/{$id}/anexo")
            ->assertOk();
    }

    public function test_engajamento_admin_ve_leituras(): void
    {
        $this->asAdmin();
        $id = $this->tenantJson('POST', '/api/v1/ecc/recados', [
            'titulo' => 'Engaja',
            'corpo' => 'x',
            'escopo' => 'paroquia',
            'status' => 'publicado',
        ])->assertCreated()->json('data.id');

        $this->asLider();
        $this->tenantJson('POST', "/api/v1/ecc/recados/{$id}/ler")->assertOk();
        $this->tenantJson('POST', "/api/v1/ecc/recados/{$id}/like")->assertOk();

        $this->asAdmin();
        $eng = $this->tenantJson('GET', "/api/v1/ecc/recados/{$id}/engajamento")->assertOk()->json('data');
        $this->assertSame(1, $eng['resumo']['leituras_count']);
        $this->assertSame(1, $eng['resumo']['likes_count']);
        $this->assertSame('Líder Alpha', $eng['leituras'][0]['nome']);

        $this->asLider();
        $this->tenantJson('GET', "/api/v1/ecc/recados/{$id}/engajamento")->assertForbidden();
    }

    public function test_reflexao_lista_resumo_e_anexo(): void
    {
        $this->asAdmin();
        $file = UploadedFile::fake()->create('estudo.docx', 80, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $id = $this->withHeaders([
            'X-Tenant' => 'paroquia-mural',
            'Accept' => 'application/json',
        ])->post('/api/v1/ecc/reflexoes', [
            'titulo' => 'Estudo',
            'texto' => str_repeat('Texto da reflexão. ', 30),
            'status' => 'publicado',
            'anexo' => $file,
        ])->assertCreated()->json('data.id');

        $this->asLider();
        $lista = $this->tenantJson('GET', '/api/v1/ecc/reflexoes')->assertOk()->json('data.0');
        $this->assertArrayHasKey('resumo', $lista);
        $this->assertArrayNotHasKey('texto', $lista);
        $this->assertTrue($lista['tem_anexo']);

        $this->tenantJson('POST', "/api/v1/ecc/reflexoes/{$id}/ler")->assertOk();
        $detalhe = $this->tenantJson('GET', "/api/v1/ecc/reflexoes/{$id}")->assertOk()->json('data');
        $this->assertArrayHasKey('texto', $detalhe);
        $this->assertTrue($detalhe['lido_por_mim']);

        $this->withHeader('X-Tenant', 'paroquia-mural')
            ->get("/api/v1/ecc/reflexoes/{$id}/anexo")
            ->assertOk();
    }
}
