<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Casal;
use App\Models\EccEquipe;
use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->deleteTenantDatabase($this->tenant->id);

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

    public function test_import_preview_classifica_new_match_identical(): void
    {
        $created = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe' => 'Equipe Alpha',
            'nome' => 'João',
            'nome_conjuge' => 'Maria',
            'telefone' => '11999990000',
            'endereco' => 'Rua Sistema, 1',
        ])->assertCreated()->json('data');

        $preview = $this->tenantJson('POST', '/api/v1/ecc/casais/import/preview', [
            'rows' => [
                [
                    'Equipe' => 'Equipe Alpha',
                    'Nome' => 'João e Maria',
                    'Endereço' => 'Rua Planilha, 99',
                    'Telefone' => '11999990000',
                ],
                [
                    'Equipe' => 'Equipe Beta',
                    'Nome' => 'Pedro e Ana',
                    'Endereço' => 'Av. Nova, 10',
                    'Telefone' => '11888887777',
                ],
                [
                    'Equipe' => 'Equipe Alpha',
                    'Nome' => 'João e Maria',
                    'Endereço' => 'Rua Sistema, 1',
                    'Telefone' => '11999990000',
                ],
            ],
        ])->assertOk()->json();

        $this->assertSame(1, $preview['summary']['match']);
        $this->assertSame(1, $preview['summary']['new']);
        $this->assertSame(1, $preview['summary']['identical']);

        $match = collect($preview['items'])->firstWhere('status', 'match');
        $this->assertNotNull($match);
        $this->assertSame($created['id'], $match['suggested_casal_id']);
        $this->assertSame('update', $match['suggested_action']);
        $enderecoDiff = collect($match['field_diffs'])->firstWhere('field', 'endereco');
        $this->assertNotNull($enderecoDiff);
        $this->assertSame('Rua Sistema, 1', $enderecoDiff['current']);
        $this->assertSame('Rua Planilha, 99', $enderecoDiff['incoming']);
        $this->assertFalse($enderecoDiff['suggest_apply']);

        $novo = collect($preview['items'])->firstWhere('status', 'new');
        $this->assertSame('create', $novo['suggested_action']);

        $identical = collect($preview['items'])->firstWhere('status', 'identical');
        $this->assertSame('skip', $identical['suggested_action']);
    }

    public function test_import_commit_atualiza_campos_escolhidos_e_cria_novos(): void
    {
        $created = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe' => 'Equipe Alpha',
            'nome' => 'Carlos',
            'nome_conjuge' => 'Beatriz',
            'telefone' => '11777776666',
            'endereco' => 'Rua Antiga, 5',
            'filhos' => '1 filho',
        ])->assertCreated()->json('data');

        $rows = [
            [
                'Equipe' => 'Equipe Alpha',
                'Nome' => 'Carlos e Beatriz',
                'Endereço' => 'Rua Nova, 50',
                'Telefone' => '11777776666 / 11666665555',
                'Têm filhos? Se sim, qual idade?' => '2 filhos',
            ],
            [
                'Equipe' => 'Equipe Gamma',
                'Nome' => 'Lucas e Helena',
                'Endereço' => 'Rua Criar, 1',
                'Telefone' => '11555554444',
            ],
        ];

        $result = $this->tenantJson('POST', '/api/v1/ecc/casais/import/commit', [
            'rows' => $rows,
            'decisions' => [
                [
                    'row_index' => 0,
                    'action' => 'update',
                    'casal_id' => $created['id'],
                    'apply_fields' => ['endereco'],
                ],
                [
                    'row_index' => 1,
                    'action' => 'create',
                ],
            ],
        ])->assertOk()->json();

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame([], $result['errors']);

        $atualizado = $this->tenantJson('GET', '/api/v1/ecc/casais/'.$created['id'])
            ->assertOk()
            ->json('data');

        $this->assertSame('Rua Nova, 50', $atualizado['endereco']);
        $this->assertSame('1 filho', $atualizado['filhos']);
        $this->assertSame('11777776666', $atualizado['telefone']);
        // telefone_conjuge da planilha NÃO foi aplicado
        $this->assertNotSame('11666665555', $atualizado['telefone_conjuge']);

        $lista = $this->tenantJson('GET', '/api/v1/ecc/casais')->assertOk()->json('data');
        $this->assertCount(2, $lista);
        $this->assertNotNull(collect($lista)->firstWhere('nome', 'Lucas'));
    }

    public function test_import_preview_ambiguous_quando_duplicata_no_banco(): void
    {
        $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe' => 'Equipe A',
            'nome' => 'Paulo',
            'nome_conjuge' => 'Clara',
            'endereco' => 'Rua 1',
        ])->assertCreated();

        $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe' => 'Equipe B',
            'nome' => 'Clara',
            'nome_conjuge' => 'Paulo',
            'endereco' => 'Rua 2',
        ])->assertCreated();

        $preview = $this->tenantJson('POST', '/api/v1/ecc/casais/import/preview', [
            'rows' => [
                [
                    'Nome' => 'Paulo e Clara',
                    'Endereço' => 'Rua Planilha',
                ],
            ],
        ])->assertOk()->json();

        $this->assertSame(1, $preview['summary']['ambiguous']);
        $item = $preview['items'][0];
        $this->assertSame('ambiguous', $item['status']);
        $this->assertCount(2, $item['candidates']);
        $this->assertSame('skip', $item['suggested_action']);
    }

    public function test_casal_pode_pertencer_a_varias_equipes(): void
    {
        $eqA = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Equipe A'])->assertCreated()->json('data');
        $eqB = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Equipe B'])->assertCreated()->json('data');

        $casal = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Rui',
            'nome_conjuge' => 'Lia',
            'equipe_ids' => [$eqA['id'], $eqB['id']],
        ])->assertCreated()->json('data');

        $this->assertCount(2, $casal['equipes']);
        $this->assertEqualsCanonicalizing([$eqA['id'], $eqB['id']], $casal['equipe_ids']);

        $this->tenantJson('PUT', '/api/v1/ecc/equipes/'.$eqA['id'].'/casais', [
            'casal_ids' => [],
        ])->assertOk();

        $atualizado = $this->tenantJson('GET', '/api/v1/ecc/casais/'.$casal['id'])->assertOk()->json('data');
        $this->assertSame([$eqB['id']], $atualizado['equipe_ids']);
        $this->assertSame($eqB['id'], $atualizado['equipe_id']);
    }

    public function test_merge_unifica_casais_e_equipes(): void
    {
        $eqA = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Alpha'])->assertCreated()->json('data');
        $eqB = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Beta'])->assertCreated()->json('data');

        $keep = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Tiago',
            'nome_conjuge' => 'Sara',
            'equipe_id' => $eqA['id'],
            'endereco' => 'Rua Keep',
        ])->assertCreated()->json('data');

        $absorb = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Tiago',
            'nome_conjuge' => 'Sara',
            'equipe_id' => $eqB['id'],
            'telefone' => '11911112222',
            'endereco' => 'Rua Absorb',
        ])->assertCreated()->json('data');

        $merged = $this->tenantJson('POST', '/api/v1/ecc/casais/merge', [
            'keep_id' => $keep['id'],
            'absorb_id' => $absorb['id'],
        ])->assertOk()->json('data');

        $this->assertSame($keep['id'], $merged['id']);
        $this->assertEqualsCanonicalizing([$eqA['id'], $eqB['id']], $merged['equipe_ids']);
        $this->assertSame('Rua Keep', $merged['endereco']);
        $this->assertSame('11911112222', $merged['telefone']);

        $this->tenantJson('GET', '/api/v1/ecc/casais/'.$absorb['id'])->assertNotFound();
        $lista = $this->tenantJson('GET', '/api/v1/ecc/casais')->assertOk()->json('data');
        $this->assertCount(1, $lista);
    }

    public function test_merge_preview_e_apply_fields_seletivo(): void
    {
        $eqA = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Gama'])->assertCreated()->json('data');
        $eqB = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Delta'])->assertCreated()->json('data');

        $keep = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Paulo',
            'nome_conjuge' => 'Lia',
            'equipe_id' => $eqA['id'],
            'endereco' => 'Rua Keep',
            'cidade' => 'Campinas',
        ])->assertCreated()->json('data');

        $absorb = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Paulo',
            'nome_conjuge' => 'Lia',
            'equipe_id' => $eqB['id'],
            'telefone' => '11999998888',
            'endereco' => 'Rua Absorb',
            'cidade' => 'Jundiaí',
        ])->assertCreated()->json('data');

        $preview = $this->tenantJson('POST', '/api/v1/ecc/casais/merge/preview', [
            'keep_id' => $keep['id'],
            'absorb_id' => $absorb['id'],
        ])->assertOk()->json();

        $this->assertSame($keep['id'], $preview['keep']['id']);
        $this->assertSame($absorb['id'], $preview['absorb']['id']);
        $this->assertEqualsCanonicalizing([$eqA['id'], $eqB['id']], array_column($preview['equipes_union'], 'id'));

        $fields = array_column($preview['field_diffs'], 'field');
        $this->assertContains('endereco', $fields);
        $this->assertContains('cidade', $fields);
        $this->assertContains('telefone', $fields);
        $this->assertNotContains('equipe', $fields);
        $this->assertContains('telefone', $preview['suggested_apply_fields']);
        // SPEC-037: "Rua Absorb" é mais longo que "Rua Keep" → sugerir absorb.
        $this->assertContains('endereco', $preview['suggested_apply_fields']);
        // "Campinas" (8) >= "Jundiaí" (7) → empate/keep mais completo → não sugerir.
        $this->assertNotContains('cidade', $preview['suggested_apply_fields']);

        $merged = $this->tenantJson('POST', '/api/v1/ecc/casais/merge', [
            'keep_id' => $keep['id'],
            'absorb_id' => $absorb['id'],
            'apply_fields' => ['cidade'],
        ])->assertOk()->json('data');

        $this->assertSame($keep['id'], $merged['id']);
        $this->assertSame('Rua Keep', $merged['endereco']);
        $this->assertSame('Jundiaí', $merged['cidade']);
        $this->assertTrue(empty($merged['telefone']));
        $this->assertEqualsCanonicalizing([$eqA['id'], $eqB['id']], $merged['equipe_ids']);
        $this->tenantJson('GET', '/api/v1/ecc/casais/'.$absorb['id'])->assertNotFound();
    }

    public function test_merge_preview_sugere_valor_mais_completo(): void
    {
        $eq = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Completo'])->assertCreated()->json('data');

        $keep = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Roberto',
            'nome_conjuge' => 'Helena',
            'equipe_id' => $eq['id'],
            'telefone' => '99998888',
            'cep' => '13000',
            'observacoes' => 'nota',
            'endereco' => 'Rua Curta',
        ])->assertCreated()->json('data');

        $absorb = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Roberto',
            'nome_conjuge' => 'Helena',
            'equipe_id' => $eq['id'],
            'telefone' => '1199998888',
            'cep' => '13000-000',
            'observacoes' => 'nota detalhada do casal',
            'endereco' => 'Rua',
            'bairro' => 'Centro',
        ])->assertCreated()->json('data');

        $preview = $this->tenantJson('POST', '/api/v1/ecc/casais/merge/preview', [
            'keep_id' => $keep['id'],
            'absorb_id' => $absorb['id'],
        ])->assertOk()->json();

        $suggested = $preview['suggested_apply_fields'];
        $this->assertContains('telefone', $suggested);
        $this->assertContains('cep', $suggested);
        $this->assertContains('observacoes', $suggested);
        $this->assertContains('bairro', $suggested);
        $this->assertNotContains('endereco', $suggested);

        $telefoneDiff = collect($preview['field_diffs'])->firstWhere('field', 'telefone');
        $this->assertTrue($telefoneDiff['suggest_apply']);
        $enderecoDiff = collect($preview['field_diffs'])->firstWhere('field', 'endereco');
        $this->assertFalse($enderecoDiff['suggest_apply']);
    }

    public function test_ativo_casal_e_equipe(): void
    {
        $eq = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Ativa',
            'ativo' => true,
        ])->assertCreated()->json('data');
        $this->assertTrue($eq['ativo']);

        $this->tenantJson('PUT', '/api/v1/ecc/equipes/'.$eq['id'], [
            'ativo' => false,
        ])->assertOk()->assertJsonPath('data.ativo', false);

        $casal = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Nuno',
            'nome_conjuge' => 'Inês',
            'equipe_id' => $eq['id'],
            'ativo' => true,
        ])->assertCreated()->json('data');
        $this->assertTrue($casal['ativo']);

        $this->tenantJson('PUT', '/api/v1/ecc/casais/'.$casal['id'], [
            'nome' => 'Nuno',
            'nome_conjuge' => 'Inês',
            'ativo' => false,
        ])->assertOk()->assertJsonPath('data.ativo', false);

        $this->assertCount(
            0,
            $this->tenantJson('GET', '/api/v1/ecc/casais?ativo=1')->assertOk()->json('data')
        );
        $this->assertCount(
            1,
            $this->tenantJson('GET', '/api/v1/ecc/casais?ativo=0')->assertOk()->json('data')
        );
    }

}
