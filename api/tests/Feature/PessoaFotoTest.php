<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PessoaFotoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin-foto@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Demo Foto',
            'slug' => 'demo-foto',
        ]);

        $response->assertCreated();
        $this->tenant = Tenant::query()->where('slug', 'demo-foto')->firstOrFail();
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
    private function tenantJson(string $method, string $uri, array $data = [])
    {
        return $this->withHeader('X-Tenant', 'demo-foto')->json($method, $uri, $data);
    }

    private function createCasal(): array
    {
        $equipe = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Foto',
        ])->assertCreated()->json('data');

        return $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $equipe['id'],
            'nome' => 'João Foto',
            'nome_conjuge' => 'Maria Foto',
        ])->assertCreated()->json('data');
    }

    public function test_upload_foto_requer_auth(): void
    {
        $casal = $this->createCasal();
        $pessoaId = $casal['ele']['id'];

        $this->app['auth']->forgetGuards();

        $this->post('/api/v1/pessoas/'.$pessoaId.'/foto', [
            'file' => UploadedFile::fake()->image('rosto.jpg'),
        ], [
            'X-Tenant' => 'demo-foto',
        ])->assertUnauthorized();
    }

    public function test_upload_e_remove_foto_atualiza_ficha_com_foto(): void
    {
        $casal = $this->createCasal();
        $this->assertFalse($casal['ficha_com_foto']);
        $this->assertNotEmpty($casal['ele']['id']);
        $this->assertNull($casal['ele']['foto_url']);

        $pessoaId = $casal['ele']['id'];

        $upload = $this->withHeader('X-Tenant', 'demo-foto')
            ->post('/api/v1/pessoas/'.$pessoaId.'/foto', [
                'file' => UploadedFile::fake()->image('rosto.jpg', 200, 200),
            ]);

        $upload->assertOk();
        $this->assertNotNull($upload->json('data.foto_url'));

        $atualizado = $this->tenantJson('GET', '/api/v1/ecc/casais/'.$casal['id'])
            ->assertOk()
            ->json('data');

        $this->assertTrue($atualizado['ficha_com_foto']);
        $this->assertNotNull($atualizado['ele']['foto_url']);

        $this->withHeader('X-Tenant', 'demo-foto')
            ->deleteJson('/api/v1/pessoas/'.$pessoaId.'/foto')
            ->assertNoContent();

        $semFoto = $this->tenantJson('GET', '/api/v1/ecc/casais/'.$casal['id'])
            ->assertOk()
            ->json('data');

        $this->assertFalse($semFoto['ficha_com_foto']);
        $this->assertNull($semFoto['ele']['foto_url']);
    }

    public function test_upload_rejeita_arquivo_invalido(): void
    {
        $casal = $this->createCasal();

        $this->withHeaders([
            'X-Tenant' => 'demo-foto',
            'Accept' => 'application/json',
        ])
            ->post('/api/v1/pessoas/'.$casal['ele']['id'].'/foto', [
                'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_import_nao_define_ficha_com_foto_sem_arquivo(): void
    {
        $result = $this->tenantJson('POST', '/api/v1/ecc/casais/import', [
            'rows' => [
                [
                    'Equipe' => 'Import Foto',
                    'Nome' => 'Ana e Bruno',
                    'Ficha Com foto:' => 'Sim',
                ],
            ],
        ])->assertOk()->json();

        $this->assertSame(1, $result['imported']);

        $lista = $this->tenantJson('GET', '/api/v1/ecc/casais')->assertOk()->json('data');
        $casal = collect($lista)->firstWhere('nome', 'Ana');
        $this->assertNotNull($casal);
        $this->assertFalse($casal['ficha_com_foto']);
    }
}
