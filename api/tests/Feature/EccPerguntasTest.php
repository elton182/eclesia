<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\EccPerguntaEnvioCriado;
use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EccPerguntasTest extends TestCase
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

        $platform = SuperAdmin::query()->create([
            'name' => 'Platform',
            'email' => 'platform-perguntas@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Perguntas',
            'slug' => 'paroquia-perguntas',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'paroquia-perguntas')->firstOrFail();
        Auth::forgetGuards();

        tenancy()->initialize($this->tenant);
        $this->admin = User::findByEmail('admin@paroquia-perguntas.local');
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
            'email' => 'lider-alpha@paroquia-perguntas.local',
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
            'email' => 'lider-beta@paroquia-perguntas.local',
            'password' => 'password123',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('PUT', "/api/v1/users/{$liderBetaId}/roles", [
            'igreja_id' => $this->igrejaId,
            'roles' => [
                ['name' => 'lider-equipe', 'equipe_ids' => [$this->equipeBetaId]],
            ],
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        $this->liderAlpha = User::findByEmail('lider-alpha@paroquia-perguntas.local');
        $this->liderBeta = User::findByEmail('lider-beta@paroquia-perguntas.local');
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
    private function tenantJson(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'X-Tenant' => $this->tenant->slug,
            'X-Igreja' => $this->igrejaId,
        ])->json($method, $uri, $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFormulario(?string $responsavelUlid = null): array
    {
        return [
            'titulo' => 'Confirmação de encontro',
            'descricao' => 'Responda até sexta',
            'mensagem_pos_envio' => 'Obrigado! A secretaria vai analisar e retornar.',
            'rotulo_resposta' => 'Retorno da secretaria',
            'responsavel_user_id' => $responsavelUlid ?? (string) $this->admin->ulid,
            'campos' => [
                [
                    'tipo' => 'texto_curto',
                    'rotulo' => 'Quantidade de casais',
                    'obrigatorio' => true,
                    'ordem' => 0,
                ],
                [
                    'tipo' => 'lista',
                    'rotulo' => 'Vai participar?',
                    'obrigatorio' => true,
                    'ordem' => 1,
                    'opcoes' => ['Sim', 'Não', 'Talvez'],
                ],
                [
                    'tipo' => 'texto_longo',
                    'rotulo' => 'Observações',
                    'obrigatorio' => false,
                    'ordem' => 2,
                ],
            ],
        ];
    }

    public function test_lider_nao_cria_formulario(): void
    {
        Sanctum::actingAs($this->liderAlpha);

        $this->tenantJson('POST', '/api/v1/ecc/perguntas', $this->payloadFormulario())
            ->assertForbidden();
    }

    public function test_fluxo_disponibilizar_enviar_responder_e_broadcast(): void
    {
        Event::fake([EccPerguntaEnvioCriado::class]);

        Sanctum::actingAs($this->admin);

        $created = $this->tenantJson('POST', '/api/v1/ecc/perguntas', $this->payloadFormulario())
            ->assertCreated()
            ->json('data');

        $this->assertSame('rascunho', $created['status']);
        $this->assertCount(3, $created['campos']);
        $this->assertSame(
            'Obrigado! A secretaria vai analisar e retornar.',
            $created['mensagem_pos_envio'],
        );
        $this->assertSame('Retorno da secretaria', $created['rotulo_resposta']);
        $perguntaId = $created['id'];
        $campoCurtoId = $created['campos'][0]['id'];
        $campoListaId = $created['campos'][1]['id'];

        $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $this->equipeAlphaId,
            'nome' => 'Carlos Piloto',
            'email' => 'carlos.piloto@paroquia-perguntas.local',
            'nome_conjuge' => 'Ana Piloto',
            'email_conjuge' => 'ana.piloto@paroquia-perguntas.local',
            'piloto' => true,
        ])->assertCreated();

        $edited = $this->tenantJson('PUT', "/api/v1/ecc/perguntas/{$perguntaId}", [
            'titulo' => 'Confirmação de encontro (atualizado)',
            'mensagem_pos_envio' => 'Recebemos sua resposta. Aguarde o retorno.',
            'rotulo_resposta' => 'Resposta da secretaria',
            'campos' => [
                [
                    'tipo' => 'texto_curto',
                    'rotulo' => 'Quantidade de casais',
                    'obrigatorio' => true,
                    'ordem' => 0,
                ],
                [
                    'tipo' => 'lista',
                    'rotulo' => 'Vai participar?',
                    'obrigatorio' => true,
                    'ordem' => 1,
                    'opcoes' => ['Sim', 'Não', 'Talvez'],
                ],
            ],
        ])->assertOk()->json('data');

        $this->assertSame('Confirmação de encontro (atualizado)', $edited['titulo']);
        $this->assertSame('Recebemos sua resposta. Aguarde o retorno.', $edited['mensagem_pos_envio']);
        $this->assertSame('Resposta da secretaria', $edited['rotulo_resposta']);
        $this->assertCount(2, $edited['campos']);
        $campoCurtoId = $edited['campos'][0]['id'];
        $campoListaId = $edited['campos'][1]['id'];

        Sanctum::actingAs($this->liderAlpha);
        $this->tenantJson('GET', '/api/v1/ecc/perguntas')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($this->admin);
        $this->tenantJson('POST', "/api/v1/ecc/perguntas/{$perguntaId}/disponibilizar")
            ->assertOk()
            ->assertJsonPath('data.status', 'disponivel');

        Sanctum::actingAs($this->liderAlpha);
        $lista = $this->tenantJson('GET', '/api/v1/ecc/perguntas')->assertOk()->json('data');
        $this->assertCount(1, $lista);

        $resumo = $this->tenantJson('GET', '/api/v1/ecc/perguntas/resumo')
            ->assertOk()
            ->json('data');
        $this->assertSame(1, $resumo['pendentes']);

        $detail = $this->tenantJson('GET', "/api/v1/ecc/perguntas/{$perguntaId}")
            ->assertOk()
            ->json('data');

        $envio = $this->tenantJson('POST', "/api/v1/ecc/perguntas/{$perguntaId}/envios", [
            'equipe_id' => $this->equipeAlphaId,
            'respostas' => [
                $campoCurtoId => '4',
                $campoListaId => 'Sim',
            ],
        ])->assertCreated()->json('data');

        $this->assertSame('enviado', $envio['status']);
        $this->assertNotEmpty($envio['enviado_em']);
        $this->assertStringContainsString('Carlos Piloto', (string) $envio['casal_piloto']);
        $this->assertStringContainsString('Ana Piloto', (string) $envio['casal_piloto']);

        Event::assertDispatched(EccPerguntaEnvioCriado::class, function (EccPerguntaEnvioCriado $e): bool {
            return $e->responsavelUlid === (string) $this->admin->ulid;
        });

        $this->tenantJson('POST', "/api/v1/ecc/perguntas/{$perguntaId}/envios", [
            'equipe_id' => $this->equipeAlphaId,
            'respostas' => [
                $campoCurtoId => '5',
                $campoListaId => 'Não',
            ],
        ])->assertStatus(422);

        Sanctum::actingAs($this->liderBeta);
        $this->tenantJson('POST', "/api/v1/ecc/perguntas/{$perguntaId}/envios", [
            'equipe_id' => $this->equipeAlphaId,
            'respostas' => [
                $campoCurtoId => '1',
                $campoListaId => 'Sim',
            ],
        ])->assertStatus(422);

        Sanctum::actingAs($this->admin);
        $envios = $this->tenantJson('GET', "/api/v1/ecc/perguntas/{$perguntaId}/envios")
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $envios);

        $respondido = $this->tenantJson('POST', "/api/v1/ecc/perguntas/envios/{$envio['id']}/responder", [
            'resposta' => 'Obrigado, confirmado na agenda.',
        ])->assertOk()->json('data');

        $this->assertSame('respondido', $respondido['status']);
        $this->assertSame('Obrigado, confirmado na agenda.', $respondido['resposta_responsavel']);
        $this->assertNotEmpty($respondido['respondido_em']);
        $this->assertStringContainsString('Carlos Piloto', (string) $respondido['casal_piloto']);

        Sanctum::actingAs($this->liderAlpha);
        $resumoComResposta = $this->tenantJson('GET', '/api/v1/ecc/perguntas/resumo')
            ->assertOk()
            ->json('data');
        $this->assertSame(0, $resumoComResposta['pendentes']);
        $this->assertSame(1, $resumoComResposta['com_resposta_nova']);

        $detailLider = $this->tenantJson('GET', "/api/v1/ecc/perguntas/{$perguntaId}")
            ->assertOk()
            ->json('data');
        $this->assertSame('Resposta da secretaria', $detailLider['rotulo_resposta']);
        $meu = $detailLider['meu_envio'];
        $this->assertSame('respondido', $meu['status']);
        $this->assertSame('Obrigado, confirmado na agenda.', $meu['resposta_responsavel']);
        $this->assertNotEmpty($meu['enviado_em']);
        $this->assertNotEmpty($meu['respondido_em']);
        $this->assertNotEmpty($meu['resposta_vista_em']);

        $resumoAposVer = $this->tenantJson('GET', '/api/v1/ecc/perguntas/resumo')
            ->assertOk()
            ->json('data');
        $this->assertSame(0, $resumoAposVer['pendentes']);
        $this->assertSame(0, $resumoAposVer['com_resposta_nova']);
    }

    public function test_nao_autenticado_recebe_401(): void
    {
        Auth::forgetGuards();

        $this->withHeaders([
            'X-Tenant' => $this->tenant->slug,
            'X-Igreja' => $this->igrejaId,
        ])->getJson('/api/v1/ecc/perguntas')->assertUnauthorized();
    }
}
