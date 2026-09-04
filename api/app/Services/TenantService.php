<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class TenantService
{
    /**
     * @param  array{name: string, slug: string}  $data
     */
    public function create(array $data): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);
    }

    /**
     * @param  array{name?: string, slug?: string}  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant->refresh();
    }

    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Tenant::query()->orderBy('name')->paginate($perPage);
    }
}
