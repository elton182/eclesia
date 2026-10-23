<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Casal;
use App\Models\EccEquipe;
use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EccEquipeAcessoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $igrejaId;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = SuperAdmin::query()->create([
            'name' => 'Platform',
            'email' => 'platform-equipe-acesso@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Acesso',
            'slug' => 'paroquia-acesso',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'paroquia-acesso')->firstOrFail();
        Auth::forgetGuards();

        tenancy()->initialize($this->tenant);
        $this->admin = User::findByEmail('admin@paroquia-acesso.local');
        $this->igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        Sanctum::actingAs($this->admin);
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
        return $this->withHeader('X-Tenant', 'paroquia-acesso')->json($method, $uri, $data);
    }

    public function test_sync_casais_define_conjunto_da_equipe(): void
    {
        $equipeA = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Equipe A'])
            ->assertCreated()
            ->json('data.id');

        $equipeB = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Equipe B'])
            ->assertCreated()
            ->json('data.id');

        $casal1 = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $equipeA,
            'nome' => 'João',
            'nome_conjuge' => 'Maria',
        ])->assertCreated()->json('data.id');

        $casal2 = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $equipeB,
            'nome' => 'Pedro',
            'nome_conjuge' => 'Ana',
        ])->assertCreated()->json('data.id');

        $casal3 = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Carlos',
            'nome_conjuge' => 'Lucia',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('PUT', "/api/v1/ecc/equipes/{$equipeA}/casais", [
            'casal_ids' => [$casal2, $casal3],
        ])->assertOk()
            ->assertJsonPath('data.casais_count', 2);

        tenancy()->initialize($this->tenant);
        $this->assertNull(Casal::query()->findOrFail($casal1)->ecc_equipe_id);
        $this->assertSame($equipeA, Casal::query()->findOrFail($casal2)->ecc_equipe_id);
        $this->assertSame($equipeA, Casal::query()->findOrFail($casal3)->ecc_equipe_id);
        tenancy()->end();
    }

    public function test_criar_acesso_gera_username_e_senha_temporaria(): void
    {
        $equipeId = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Equipe Caná'])
            ->assertCreated()
            ->json('data.id');

        $acesso = $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeId}/acesso")
            ->assertCreated()
            ->json('data');

        $this->assertSame('equipe-cana', $acesso['username']);
        $this->assertSame('1234', $acesso['password']);
        $this->assertTrue($acesso['tem_acesso']);

        $this->tenantJson('GET', "/api/v1/ecc/equipes/{$equipeId}")
            ->assertOk()
            ->assertJsonPath('data.tem_acesso', true)
            ->assertJsonPath('data.acesso_username', 'equipe-cana');

        $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeId}/acesso")
            ->assertStatus(422);

        Auth::forgetGuards();

        $login = $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-acesso',
            'login' => 'equipe-cana',
            'password' => $acesso['password'],
        ])->assertOk();

        $this->assertTrue($login->json('user.must_change_password'));

        $token = $login->json('access_token');

        $this->withHeader('X-Tenant', 'paroquia-acesso')
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/ecc/equipes')
            ->assertForbidden()
            ->assertJsonPath('code', 'must_change_password');

        $this->withHeader('X-Tenant', 'paroquia-acesso')
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/web/change-password', [
                'current_password' => $acesso['password'],
                'password' => 'NovaSenha123!',
                'password_confirmation' => 'NovaSenha123!',
            ])
            ->assertOk()
            ->assertJsonPath('must_change_password', false);

        $this->withHeader('X-Tenant', 'paroquia-acesso')
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/ecc/equipes')
            ->assertOk();
    }

    public function test_reset_senha_acesso_exige_troca_no_proximo_login(): void
    {
        $equipeId = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Equipe Reset'])
            ->assertCreated()
            ->json('data.id');

        $acesso = $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeId}/acesso")
            ->assertCreated()
            ->json('data');

        Sanctum::actingAs($this->admin);

        $reset = $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeId}/acesso/reset-senha")
            ->assertOk()
            ->json('data');

        $this->assertSame('equipe-reset', $reset['username']);
        $this->assertSame('1234', $reset['password']);

        Auth::forgetGuards();

        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-acesso',
            'login' => 'equipe-reset',
            'password' => $reset['password'],
        ])->assertOk()
            ->assertJsonPath('user.must_change_password', true);
    }

    public function test_renomear_equipe_nao_altera_username(): void
    {
        $equipeId = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Equipe Original'])
            ->assertCreated()
            ->json('data.id');

        $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeId}/acesso")
            ->assertCreated();

        $this->tenantJson('PUT', "/api/v1/ecc/equipes/{$equipeId}", [
            'nome' => 'Equipe Renomeada',
        ])->assertOk()
            ->assertJsonPath('data.nome', 'EQUIPE RENOMEADA')
            ->assertJsonPath('data.acesso_username', 'equipe-original');
    }

    public function test_reset_sem_acesso_retorna_422(): void
    {
        $equipeId = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Sem Acesso'])
            ->assertCreated()
            ->json('data.id');

        $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeId}/acesso/reset-senha")
            ->assertStatus(422);
    }

    public function test_alterar_login_atualiza_username_e_permite_novo_login(): void
    {
        $equipeId = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Equipe Login'])
            ->assertCreated()
            ->json('data.id');

        $acesso = $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeId}/acesso")
            ->assertCreated()
            ->json('data');

        Auth::forgetGuards();

        $loginAntigo = $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-acesso',
            'login' => 'equipe-login',
            'password' => $acesso['password'],
        ])->assertOk();

        $tokenAntigo = $loginAntigo->json('access_token');

        Sanctum::actingAs($this->admin);

        $alterado = $this->tenantJson('PUT', "/api/v1/ecc/equipes/{$equipeId}/acesso", [
            'username' => 'Cana Nova!',
        ])->assertOk()
            ->json('data');

        $this->assertSame('cana-nova', $alterado['username']);
        $this->assertSame('cana-nova', $alterado['acesso_username']);
        $this->assertTrue($alterado['tem_acesso']);
        $this->assertArrayNotHasKey('password', $alterado);

        $this->tenantJson('GET', "/api/v1/ecc/equipes/{$equipeId}")
            ->assertOk()
            ->assertJsonPath('data.acesso_username', 'cana-nova');

        Auth::forgetGuards();

        $this->withHeader('X-Tenant', 'paroquia-acesso')
            ->withHeader('Authorization', 'Bearer '.$tokenAntigo)
            ->postJson('/api/v1/web/me')
            ->assertUnauthorized();

        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-acesso',
            'login' => 'equipe-login',
            'password' => $acesso['password'],
        ])->assertForbidden();

        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-acesso',
            'login' => 'cana-nova',
            'password' => $acesso['password'],
        ])->assertOk()
            ->assertJsonPath('user.username', 'cana-nova');
    }

    public function test_alterar_login_sem_acesso_retorna_422(): void
    {
        $equipeId = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Sem Login'])
            ->assertCreated()
            ->json('data.id');

        $this->tenantJson('PUT', "/api/v1/ecc/equipes/{$equipeId}/acesso", [
            'username' => 'qualquer',
        ])->assertStatus(422);
    }

    public function test_alterar_login_username_duplicado_retorna_422(): void
    {
        $equipeA = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Equipe Alpha'])
            ->assertCreated()
            ->json('data.id');
        $equipeB = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Equipe Beta'])
            ->assertCreated()
            ->json('data.id');

        $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeA}/acesso")->assertCreated();
        $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeB}/acesso")->assertCreated();

        $this->tenantJson('PUT', "/api/v1/ecc/equipes/{$equipeB}/acesso", [
            'username' => 'equipe-alpha',
        ])->assertStatus(422);
    }

    public function test_login_ainda_aceita_email_como_alias(): void
    {
        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-acesso',
            'email' => 'admin@paroquia-acesso.local',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_pdf_equipes_inclui_piloto_e_login(): void
    {
        $equipeId = $this->tenantJson('POST', '/api/v1/ecc/equipes', ['nome' => 'Equipe PDF'])
            ->assertCreated()
            ->json('data.id');

        $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeId}/acesso")
            ->assertCreated();

        $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $equipeId,
            'nome' => 'João Piloto',
            'nome_conjuge' => 'Maria Piloto',
            'piloto' => true,
        ])->assertCreated();

        $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $equipeId,
            'nome' => 'Pedro Comum',
            'nome_conjuge' => 'Ana Comum',
            'piloto' => false,
        ])->assertCreated();

        $pdf = $this->tenantJson('GET', '/api/v1/ecc/equipes/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertNotEmpty($pdf->getContent());
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_pdf_equipes_sem_permissao_manage_retorna_403(): void
    {
        tenancy()->initialize($this->tenant);
        $lider = User::query()->create([
            'name' => 'Líder PDF',
            'email' => 'lider-pdf@paroquia-acesso.local',
            'password' => 'password',
            'is_active' => true,
        ]);
        tenancy()->end();

        Sanctum::actingAs($lider);

        $this->tenantJson('GET', '/api/v1/ecc/equipes/pdf')
            ->assertForbidden();
    }
}
