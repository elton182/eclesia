<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Igreja;
use App\Models\Pastoral;
use App\Models\PlanejamentoAnual;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Database\Seeder;

/**
 * Seed de desenvolvimento: super-admin + tenant demo com 2 igrejas.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SuperAdmin::query()->updateOrCreate(
            ['email' => 'admin@eclesia.local'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
            ],
        );

        $this->seedDemoTenant();
    }

    private function seedDemoTenant(): void
    {
        $existing = Tenant::query()->where('slug', 'demo')->first();

        if ($existing === null) {
            /** @var TenantService $tenants */
            $tenants = app(TenantService::class);
            $existing = $tenants->create([
                'name' => 'Organização Demo',
                'slug' => 'demo',
                'aliases' => ['demo-org'],
            ]);
        }

        $existing->run(function (): void {
            $first = Igreja::query()->orderBy('created_at')->first();
            if ($first === null) {
                $first = Igreja::query()->create([
                    'nome' => 'Paróquia São José',
                    'tipo' => 'paroquia',
                    'cidade' => 'São Paulo',
                    'uf' => 'SP',
                ]);
            } else {
                $first->fill([
                    'nome' => $first->nome ?: 'Paróquia São José',
                    'tipo' => $first->tipo ?: 'paroquia',
                ])->save();
            }

            if (Igreja::query()->count() < 2) {
                Igreja::query()->create([
                    'nome' => 'Comunidade Luz',
                    'tipo' => 'comunidade',
                    'cidade' => 'Campinas',
                    'uf' => 'SP',
                ]);
            }

            $this->seedDemoPastoraisPlanejamento($first->fresh() ?? Igreja::query()->orderBy('created_at')->firstOrFail());
        });
    }

    private function seedDemoPastoraisPlanejamento(Igreja $igreja): void
    {
        $pastorais = [
            'Pastoral da Criança',
            'PASCOM',
            'Setor Juventude',
        ];

        $created = [];
        foreach ($pastorais as $i => $nome) {
            $created[] = Pastoral::query()->firstOrCreate(
                [
                    'igreja_id' => $igreja->id,
                    'nome' => $nome,
                ],
                [
                    'ordem' => $i + 1,
                    'ativa' => true,
                    'publicado_no_site' => false,
                ],
            );
        }

        $ano = (int) date('Y') + 1;
        PlanejamentoAnual::query()->firstOrCreate(
            [
                'igreja_id' => $igreja->id,
                'ano' => $ano,
            ],
            [
                'status' => PlanejamentoAnual::STATUS_COLETA,
            ],
        );

        $coord = User::findByEmail('coordenador@demo.local');
        if ($coord === null) {
            $coord = User::query()->create([
                'name' => 'Coord. Pastoral Demo',
                'email' => 'coordenador@demo.local',
                'password' => 'password',
                'is_active' => true,
            ]);
        }

        setPermissionsTeamId($igreja->id);
        if (! $coord->hasRole('coordenador-pastoral')) {
            $coord->assignRole('coordenador-pastoral');
        }
        setPermissionsTeamId(null);

        $pastoral = $created[0];
        if (! $pastoral->membros()->where('users.id', $coord->id)->exists()) {
            $pastoral->membros()->attach($coord->id, ['papel' => 'coordenador']);
        }
    }
}
