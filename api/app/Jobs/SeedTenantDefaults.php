<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Igreja;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

/**
 * Seed padrão após migrar o banco do tenant: igreja, papéis e admin inicial.
 */
class SeedTenantDefaults implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public TenantWithDatabase $tenant) {}

    public function handle(): void
    {
        $this->tenant->run(function (): void {
            (new RolesAndPermissionsSeeder)->run();

            if (! Igreja::query()->exists()) {
                Igreja::query()->create([
                    'nome' => $this->tenant->name ?? 'Igreja principal',
                ]);
            }

            if (User::query()->count() === 0) {
                $adminEmail = 'admin@'.$this->tenant->slug.'.local';

                $user = User::query()->create([
                    'name' => 'Administrador',
                    'email' => $adminEmail,
                    'password' => 'password',
                    'is_active' => true,
                ]);

                setPermissionsTeamId(null);
                $user->assignRole('admin-tenant');
            }
        });
    }
}
