<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClientDiagnostic;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ClientDiagnosticService
{
    public const MAX_EVENTS = 100;

    public const MAX_NOTA = 500;

    /**
     * @param  array{motivo?: string, nota?: string|null, meta?: array<string, mixed>, events?: list<array<string, mixed>>}  $payload
     */
    public function store(User $user, array $payload, Request $request, ?string $igrejaId = null): ClientDiagnostic
    {
        $events = array_values(array_slice($payload['events'] ?? [], -self::MAX_EVENTS));
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        return ClientDiagnostic::query()->create([
            'user_id' => $user->id,
            'igreja_id' => $igrejaId,
            'motivo' => (string) ($payload['motivo'] ?? 'geral'),
            'nota' => isset($payload['nota']) ? mb_substr((string) $payload['nota'], 0, self::MAX_NOTA) : null,
            'meta' => $meta,
            'events' => $events,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
        ]);
    }

    /**
     * @param  array{q?: string, motivo?: string, page?: int, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, ClientDiagnostic>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 25)));

        $query = ClientDiagnostic::query()
            ->with(['user:id,ulid,name,username'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! empty($filters['motivo'])) {
            $query->where('motivo', $filters['motivo']);
        }

        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($q): void {
                $builder->where('nota', 'like', $q)
                    ->orWhere('ulid', 'like', $q)
                    ->orWhereHas('user', function ($userQuery) use ($q): void {
                        $userQuery->where('name', 'like', $q)
                            ->orWhere('username', 'like', $q);
                    });
            });
        }

        return $query->paginate($perPage);
    }

    public function findByUlid(string $ulid): ?ClientDiagnostic
    {
        return ClientDiagnostic::query()
            ->with(['user:id,ulid,name,username'])
            ->where('ulid', $ulid)
            ->first();
    }
}
