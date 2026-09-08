<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantAlias;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantService
{
    public function __construct(private readonly TenantResolver $resolver) {}

    /**
     * @param  array{name: string, slug: string, aliases?: list<string>}  $data
     */
    public function create(array $data): Tenant
    {
        // Não usar DB::transaction: CreateDatabase/Migrate fazem DDL (commit implícito no MySQL).
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);

        $this->syncAliases($tenant, $data['aliases'] ?? []);

        return $tenant->load('aliases');
    }

    /**
     * @param  array{name?: string, slug?: string, aliases?: list<string>}  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        return DB::transaction(function () use ($tenant, $data) {
            $payload = collect($data)->only(['name', 'slug'])->all();
            if ($payload !== []) {
                $tenant->update($payload);
            }

            if (array_key_exists('aliases', $data)) {
                $this->syncAliases($tenant, $data['aliases'] ?? []);
            }

            return $tenant->refresh()->load('aliases');
        });
    }

    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Tenant::query()
            ->with('aliases')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @param  list<string>  $aliases
     */
    public function syncAliases(Tenant $tenant, array $aliases): void
    {
        $normalized = $this->resolver->normalizeAliases($aliases);

        foreach ($normalized as $alias) {
            if ($alias === $tenant->slug) {
                throw ValidationException::withMessages([
                    'aliases' => ["O apelido \"{$alias}\" não pode ser igual ao slug."],
                ]);
            }

            $taken = TenantAlias::query()
                ->where('alias', $alias)
                ->where('tenant_id', '!=', $tenant->id)
                ->exists();

            if ($taken || Tenant::query()->where('slug', $alias)->where('id', '!=', $tenant->id)->exists()) {
                throw ValidationException::withMessages([
                    'aliases' => ["O apelido \"{$alias}\" já está em uso."],
                ]);
            }
        }

        $tenant->aliases()->delete();

        foreach ($normalized as $alias) {
            $tenant->aliases()->create(['alias' => $alias]);
        }
    }
}
