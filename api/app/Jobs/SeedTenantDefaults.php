<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Igreja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

/**
 * Cria a igreja padrão após o banco do tenant ser migrado.
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
            if (Igreja::query()->exists()) {
                return;
            }

            Igreja::query()->create([
                'nome' => $this->tenant->name ?? 'Igreja principal',
            ]);
        });
    }
}
