<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserLoginSemEmailTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'platform-login-sem-email@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Login',
            'slug' => 'paroquia-login',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'paroquia-login')->firstOrFail();

        Auth::forgetGuards();

        tenancy()->initialize($this->tenant);
        $this->admin = User::findByEmail('admin@paroquia-login.local');
        $this->assertNotNull($this->admin);
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

    public function test_criar_usuario_somente_com_username(): void
    {
        Sanctum::actingAs($this->admin);

        $create = $this->withHeader('X-Tenant', 'paroquia-login')
            ->postJson('/api/v1/users', [
                'name' => 'Monitor',
                'username' => 'Monitor Sala!',
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Monitor')
            ->assertJsonPath('data.username', 'monitor-sala')
            ->assertJsonPath('data.email', null);

        $ulid = $create->json('data.id');

        $this->tenant->run(function () use ($ulid): void {
            $user = User::query()->where('ulid', $ulid)->firstOrFail();
            $this->assertSame('monitor-sala', $user->username);
            $this->assertSame('monitor-sala@login.local', $user->email);
            $this->assertNull($user->publicEmail());
        });
    }

    public function test_login_com_username_sem_email_real(): void
    {
        Sanctum::actingAs($this->admin);

        $this->withHeader('X-Tenant', 'paroquia-login')
            ->postJson('/api/v1/users', [
                'name' => 'Secretaria',
                'username' => 'secretaria',
                'password' => 'password123',
            ])
            ->assertCreated();

        Auth::forgetGuards();

        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-login',
            'login' => 'secretaria',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.username', 'secretaria')
            ->assertJsonPath('user.email', null);
    }

    public function test_criar_exige_email_ou_username(): void
    {
        Sanctum::actingAs($this->admin);

        $this->withHeader('X-Tenant', 'paroquia-login')
            ->postJson('/api/v1/users', [
                'name' => 'Sem Identificador',
                'password' => 'password123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'username']);
    }

    public function test_username_duplicado_retorna_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->withHeader('X-Tenant', 'paroquia-login')
            ->postJson('/api/v1/users', [
                'name' => 'Um',
                'username' => 'mesmo-login',
                'password' => 'password123',
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant', 'paroquia-login')
            ->postJson('/api/v1/users', [
                'name' => 'Dois',
                'username' => 'mesmo-login',
                'password' => 'password123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_username_invalido_retorna_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->withHeader('X-Tenant', 'paroquia-login')
            ->postJson('/api/v1/users', [
                'name' => 'Inválido',
                'username' => '@@@',
                'password' => 'password123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_atualizar_username_de_usuario_sintetico(): void
    {
        Sanctum::actingAs($this->admin);

        $ulid = $this->withHeader('X-Tenant', 'paroquia-login')
            ->postJson('/api/v1/users', [
                'name' => 'Voluntário',
                'username' => 'vol-um',
                'password' => 'password123',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withHeader('X-Tenant', 'paroquia-login')
            ->patchJson("/api/v1/users/{$ulid}", [
                'username' => 'vol-dois',
            ])
            ->assertOk()
            ->assertJsonPath('data.username', 'vol-dois')
            ->assertJsonPath('data.email', null);

        $this->tenant->run(function () use ($ulid): void {
            $user = User::query()->where('ulid', $ulid)->firstOrFail();
            $this->assertSame('vol-dois', $user->username);
            $this->assertSame('vol-dois@login.local', $user->email);
        });

        Auth::forgetGuards();

        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-login',
            'login' => 'vol-dois',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_nao_permite_remover_email_e_username(): void
    {
        Sanctum::actingAs($this->admin);

        $ulid = $this->withHeader('X-Tenant', 'paroquia-login')
            ->postJson('/api/v1/users', [
                'name' => 'Com Email',
                'email' => 'com@paroquia-login.local',
                'password' => 'password123',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withHeader('X-Tenant', 'paroquia-login')
            ->patchJson("/api/v1/users/{$ulid}", [
                'email' => null,
                'username' => null,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_criar_com_email_e_username(): void
    {
        Sanctum::actingAs($this->admin);

        $this->withHeader('X-Tenant', 'paroquia-login')
            ->postJson('/api/v1/users', [
                'name' => 'Completo',
                'email' => 'completo@paroquia-login.local',
                'username' => 'completo',
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'completo@paroquia-login.local')
            ->assertJsonPath('data.username', 'completo');

        Auth::forgetGuards();

        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-login',
            'login' => 'completo',
            'password' => 'password123',
        ])->assertOk();

        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-login',
            'email' => 'completo@paroquia-login.local',
            'password' => 'password123',
        ])->assertOk();
    }
}
