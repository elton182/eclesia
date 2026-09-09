<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
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
            if (Igreja::query()->count() >= 2) {
                return;
            }

            $first = Igreja::query()->orderBy('created_at')->first();
            if ($first === null) {
                Igreja::query()->create([
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
        });
    }
}
