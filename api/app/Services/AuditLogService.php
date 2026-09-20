<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogService
{
    /**
     * @param  array{
     *   action?: string|null,
     *   auditable_type?: string|null,
     *   actor_user_ulid?: string|null,
     *   desde?: string|null,
     *   ate?: string|null,
     *   q?: string|null,
     *   per_page?: int|null
     * }  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['auditable_type'])) {
            $type = $filters['auditable_type'];
            if (! str_contains($type, '\\')) {
                $type = 'App\\Models\\'.$type;
            }
            $query->where('auditable_type', $type);
        }

        if (! empty($filters['actor_user_ulid'])) {
            $actorId = User::query()->where('ulid', $filters['actor_user_ulid'])->value('id');
            if ($actorId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('actor_user_id', $actorId);
            }
        }

        if (! empty($filters['desde'])) {
            $query->where('created_at', '>=', $filters['desde']);
        }

        if (! empty($filters['ate'])) {
            $query->where('created_at', '<=', $filters['ate']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('actor_label', 'like', $term)
                    ->orWhere('auditable_label', 'like', $term);
            });
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 25)));

        return $query->paginate($perPage);
    }
}
