<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Casal;
use App\Models\EccEquipe;
use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EccEquipeCasalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Demo',
            'slug' => 'demo',
        ]);

        $response->assertCreated();
        $this->tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
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
        return $this->withHeader('X-Tenant', 'demo')->json($method, $uri, $data);
    }

    public function test_ecc_requires_tenant_header(): void
    {
        $this->getJson('/api/v1/ecc/equipes')->assertStatus(400);
    }

    public function test_super_admin_bearer_can_list_equipes(): void
    {
        $token = $this->admin->createToken('admin')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Tenant', 'demo')
            ->getJson('/api/v1/ecc/equipes')
            ->assertOk();
    }

    public function test_can_manage_equipes_and_casais(): void
    {
        $equipe = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe A',
            'cor' => '#00234E',
        ])->assertCreated()
            ->json('data');

        $this->assertSame('EQUIPE A', $equipe['nome']);

        $casal = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $equipe['id'],
            'nome' => 'João Silva',
            'email' => 'joao@example.com',
            'nome_conjuge' => 'Maria Silva',
            'email_conjuge' => 'maria@example.com',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
            'data_casamento' => '10/05/2010',
        ])->assertCreated()
            ->json('data');

        $this->assertSame('João Silva', $casal['nome']);
        $this->assertSame('Maria Silva', $casal['nome_conjuge']);
        $this->assertSame('João Silva', $casal['ele']['nome']);
        $this->assertSame('Maria Silva', $casal['ela']['nome']);
        $this->assertSame('M', $casal['sexo']);
        $this->assertSame('F', $casal['sexo_conjuge']);
        $this->assertSame('EQUIPE A', $casal['equipe_nome']);

        $this->tenantJson('GET', '/api/v1/ecc/casais')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_swap_ele_ela_corrige_ordem_invertida(): void
    {
        // Cadastro com esposa no campo Ele (ordem invertida na planilha/formulário)
        $casal = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Maria Invertida',
            'email' => 'maria@example.com',
            'nome_conjuge' => 'João Invertido',
            'email_conjuge' => 'joao@example.com',
        ])->assertCreated()->json('data');

        $this->assertSame('Maria Invertida', $casal['ele']['nome']);
        $this->assertSame('João Invertido', $casal['ela']['nome']);

        $depois = $this->tenantJson('POST', '/api/v1/ecc/casais/'.$casal['id'].'/swap')
            ->assertOk()
            ->json('data');

        $this->assertSame('João Invertido', $depois['ele']['nome']);
        $this->assertSame('Maria Invertida', $depois['ela']['nome']);
        $this->assertSame('M', $depois['sexo']);
        $this->assertSame('F', $depois['sexo_conjuge']);
        $this->assertSame('João Invertido', $depois['nome']);
        $this->assertSame('Maria Invertida', $depois['nome_conjuge']);
    }

    public function test_ele_ela_respeitam_sexo_mesmo_com_ordem_a_b_invertida(): void
    {
        $casal = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Maria',
            'nome_conjuge' => 'João',
        ])->assertCreated()->json('data');

        $this->tenant->run(function () use ($casal): void {
            $model = Casal::query()->with(['pessoaA', 'pessoaB'])->findOrFail($casal['id']);
            $model->pessoaA->update(['sexo' => 'F']);
            $model->pessoaB->update(['sexo' => 'M']);
        });

        $data = $this->tenantJson('GET', '/api/v1/ecc/casais/'.$casal['id'])
            ->assertOk()
            ->json('data');

        $this->assertSame('João', $data['ele']['nome']);
        $this->assertSame('Maria', $data['ela']['nome']);
        // Campos legados continuam na ordem A/B de cadastro
        $this->assertSame('Maria', $data['nome']);
        $this->assertSame('João', $data['nome_conjuge']);
    }

    public function test_import_casais_from_spreadsheet_rows(): void
    {
        $result = $this->tenantJson('POST', '/api/v1/ecc/casais/import', [
            'rows' => [
                [
                    'equipe' => 'EQUIPE B',
                    'nome' => 'Carlos',
                    'e-mail' => 'carlos@example.com',
                    'nome conjuge' => 'Ana',
                    'e-mail conjuge' => 'ana@example.com',
                    'cidade' => 'Campinas',
                    'uf' => 'SP',
                ],
                [
                    'equipe' => 'EQUIPE B',
                    'nome' => 'Pedro',
                    'nome conjuge' => 'Paula',
                ],
            ],
        ])->assertOk()
            ->json();

        $this->assertSame(2, $result['imported']);
        $this->assertSame([], $result['errors']);

        $this->tenant->run(function () {
            $this->assertSame(1, EccEquipe::query()->count());
            $this->assertSame(2, Casal::query()->count());
            $this->assertSame(1, Igreja::query()->count());
        });
    }

    public function test_import_formato_ecc_xlsx_nome_combinado(): void
    {
        $result = $this->tenantJson('POST', '/api/v1/ecc/casais/import', [
            'rows' => [
                [
                    'Nº ' => 1,
                    'Equipe' => 'Nossa Senhora da Glória',
                    'Nome' => 'Luiz e Vera Lúcia',
                    'Piloto' => 'SIM',
                    'Endereço' => 'Rua das Flores, 10',
                    'Telefone' => '99976-1273 / 99858-8043',
                    'Têm filhos? Se sim, qual idade?' => '2 (já adultos)',
                    'Quanto tempo de casados?' => 40,
                    'Qual ECC vocês fizeram? ' => '8º',
                    'Já trabalharam no encontro do ECC?' => 'Sim, Coordenou Cozinha',
                    'Em qual função você gostaria de trabalhar?' => 'Café',
                    'Casal dirigente? Qual função' => 'Não',
                    'Já foi Cordenador Geral?' => 'Não',
                    'Já foi circulo? Tem mais Equipes?' => 'Sim',
                    'Ficha Com foto:' => 'Sim',
                    'Tem 2ª Etapa' => '11º',
                    'Tem 3ª Etapa' => 'Não',
                    'Indicação para 2025' => 'Acolhida',
                    'Função_1' => '',
                    'Aceitou_1' => '',
                ],
                [
                    'Equipe' => 'Nossa Senhora da Glória',
                    'Nome' => 'Osébio e Célia',
                    'Endereço' => 'Av. Central, 100',
                    'Telefone' => '97286-3250',
                ],
            ],
        ])->assertOk()
            ->json();

        $this->assertSame(2, $result['imported'], json_encode($result['errors']));
        $this->assertSame([], $result['errors']);

        $lista = $this->tenantJson('GET', '/api/v1/ecc/casais')->assertOk()->json('data');
        $this->assertCount(2, $lista);

        $primeiro = collect($lista)->firstWhere('nome', 'Luiz');
        $this->assertNotNull($primeiro);
        $this->assertSame('Vera Lúcia', $primeiro['nome_conjuge']);
        $this->assertSame('NOSSA SENHORA DA GLÓRIA', $primeiro['equipe_nome']);
        $this->assertSame('99976-1273', $primeiro['telefone']);
        $this->assertSame('99858-8043', $primeiro['telefone_conjuge']);
        $this->assertTrue($primeiro['piloto']);
        $this->assertSame(40, $primeiro['anos_casados']);
        $this->assertSame('8º', $primeiro['ecc_origem']);
        $this->assertFalse($primeiro['ficha_com_foto']);
        $this->assertStringContainsString('Já foi circulo', (string) $primeiro['observacoes']);
        $this->assertStringNotContainsString('Indicação para 2025', (string) $primeiro['observacoes']);
        $this->assertStringNotContainsString('Piloto:', (string) $primeiro['observacoes']);

        // SPEC-015: planilha enriquece etapas / atividades / preferências
        $etapas = collect($primeiro['etapas'] ?? []);
        $this->assertSame('8º', $etapas->firstWhere('etapa', 1)['ecc_numero'] ?? null);
        $this->assertSame('11º', $etapas->firstWhere('etapa', 2)['ecc_numero'] ?? null);

        $prefs = collect($primeiro['preferencias'] ?? []);
        $this->assertTrue($prefs->contains(fn ($p) => ($p['equipe_servico_nome'] ?? '') === 'Café e Minimercado'
            || str_contains(mb_strtolower((string) ($p['equipe_servico_nome'] ?? '')), 'café')));

        $ativ = collect($primeiro['atividades'] ?? []);
        $histCozinha = $ativ->first(fn ($a) => ($a['ecc_numero'] ?? '') === 'hist'
            && str_contains(mb_strtolower((string) ($a['equipe_servico_nome'] ?? '')), 'cozinha'));
        $this->assertNotNull($histCozinha);
        $this->assertSame('C', $histCozinha['status']);

        $ind2025 = $ativ->first(fn ($a) => ($a['ecc_numero'] ?? '') === '2025'
            && str_contains(mb_strtolower((string) ($a['equipe_servico_nome'] ?? '')), 'acolhida'));
        $this->assertNotNull($ind2025);
        $this->assertSame('IC', $ind2025['status']);
    }
}
