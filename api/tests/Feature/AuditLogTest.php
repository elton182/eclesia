<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'platform@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Audit',
            'slug' => 'paroquia-audit',
        ]);
        $response->assertCreated();
        $this->tenant = Tenant::query()->where('slug', 'paroquia-audit')->firstOrFail();

        Auth::forgetGuards();

        $this->tenant->run(function (): void {
            $this->admin = User::findByEmail('admin@paroquia-audit.local');
            $this->assertNotNull($this->admin);
        });
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

    public function test_login_success_cria_audit_log(): void
    {
        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-audit',
            'email' => 'admin@paroquia-audit.local',
            'password' => 'password',
        ])->assertOk();

        $this->tenant->run(function (): void {
            $log = AuditLog::query()->where('action', AuditLogger::ACTION_LOGIN_SUCCESS)->latest('id')->first();
            $this->assertNotNull($log);
            $this->assertSame(AuditLogger::ACTOR_USER, $log->actor_type);
            $this->assertSame($this->admin->id, $log->actor_user_id);
            $this->assertIsArray($log->metadata);
            $this->assertArrayHasKey('email_hint', $log->metadata);
            $this->assertStringContainsString('***@', $log->metadata['email_hint']);
        });
    }

    public function test_login_failed_cria_audit_log(): void
    {
        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-audit',
            'email' => 'admin@paroquia-audit.local',
            'password' => 'wrong',
        ])->assertForbidden();

        $this->tenant->run(function (): void {
            $log = AuditLog::query()->where('action', AuditLogger::ACTION_LOGIN_FAILED)->latest('id')->first();
            $this->assertNotNull($log);
            $this->assertSame(AuditLogger::ACTOR_ANONYMOUS, $log->actor_type);
            $this->assertNull($log->actor_user_id);
        });
    }

    public function test_criar_usuario_gera_log_sem_senha_em_claro(): void
    {
        Sanctum::actingAs($this->admin);

        $this->withHeader('X-Tenant', 'paroquia-audit')
            ->postJson('/api/v1/users', [
                'name' => 'Secretaria',
                'email' => 'sec@paroquia-audit.local',
                'password' => 'senha-secreta-123',
            ])
            ->assertCreated();

        $this->tenant->run(function (): void {
            $created = User::findByEmail('sec@paroquia-audit.local');
            $this->assertNotNull($created);

            $log = AuditLog::query()
                ->where('action', AuditLogger::ACTION_CREATED)
                ->where('auditable_type', User::class)
                ->where('auditable_id', (string) $created->id)
                ->latest('id')
                ->first();

            $this->assertNotNull($log);
            $this->assertSame($this->admin->id, $log->actor_user_id);
            $new = $log->new_values ?? [];
            if (isset($new['password'])) {
                $this->assertSame('[alterado]', $new['password']);
            }
            $this->assertNotSame('senha-secreta-123', $new['password'] ?? null);
            if (isset($new['email'])) {
                $this->assertSame('[alterado]', $new['email']);
            }
            if (isset($new['name'])) {
                $this->assertSame('[alterado]', $new['name']);
            }
        });
    }

    public function test_listar_audit_logs_requer_permissao(): void
    {
        $this->withHeader('X-Tenant', 'paroquia-audit')
            ->getJson('/api/v1/audit-logs')
            ->assertUnauthorized();

        $this->tenant->run(function (): void {
            $limited = User::query()->create([
                'name' => 'Líder',
                'email' => 'lider@paroquia-audit.local',
                'password' => 'password',
                'is_active' => true,
            ]);
            $igreja = Igreja::query()->first();
            setPermissionsTeamId($igreja?->id);
            $limited->assignRole('lider-equipe');
            setPermissionsTeamId(null);

            Sanctum::actingAs($limited);
        });

        $this->withHeader('X-Tenant', 'paroquia-audit')
            ->getJson('/api/v1/audit-logs')
            ->assertForbidden();
    }

    public function test_admin_lista_audit_logs(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-audit',
            'email' => 'admin@paroquia-audit.local',
            'password' => 'password',
        ])->assertOk();

        Sanctum::actingAs($this->admin);

        $response = $this->withHeader('X-Tenant', 'paroquia-audit')
            ->getJson('/api/v1/audit-logs?action=login_success')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'action', 'actor_type', 'created_at']]]);

        $this->assertNotEmpty($response->json('data'));
        $this->assertSame('login_success', $response->json('data.0.action'));
    }

    public function test_logout_cria_audit_log(): void
    {
        $login = $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-audit',
            'email' => 'admin@paroquia-audit.local',
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('access_token');

        $this->withHeader('X-Tenant', 'paroquia-audit')
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/web/logout')
            ->assertOk();

        $this->tenant->run(function (): void {
            $log = AuditLog::query()->where('action', AuditLogger::ACTION_LOGOUT)->latest('id')->first();
            $this->assertNotNull($log);
            $this->assertSame($this->admin->id, $log->actor_user_id);
        });
    }
}
