<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Igreja;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class IgrejaService
{
    public function __construct(
        private readonly IgrejaAccessService $access,
    ) {}

    /**
     * @return Collection<int, Igreja>
     */
    public function listAccessible(mixed $actor): Collection
    {
        $ids = $this->access->accessibleIds($actor);

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return Igreja::query()
            ->whereIn('id', $ids)
            ->orderBy('nome')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Igreja
    {
        return Igreja::query()->create($this->normalize($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Igreja $igreja, array $data): Igreja
    {
        $igreja->fill($this->normalize($data));
        $igreja->save();

        return $igreja->refresh();
    }

    public function delete(Igreja $igreja): void
    {
        $hasLinks = $igreja->pessoas()->exists()
            || $igreja->equipes()->exists()
            || $igreja->casais()->exists();

        if ($hasLinks) {
            throw new ConflictHttpException(
                'Não é possível excluir a igreja enquanto houver pessoas, equipes ou casais vinculados.'
            );
        }

        if (Igreja::query()->count() <= 1) {
            throw ValidationException::withMessages([
                'igreja' => ['É necessário manter ao menos uma igreja no tenant.'],
            ]);
        }

        DB::transaction(static function () use ($igreja): void {
            $igreja->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if (isset($data['uf']) && is_string($data['uf'])) {
            $data['uf'] = strtoupper($data['uf']);
        }

        if (isset($data['tipo']) && is_string($data['tipo'])) {
            $data['tipo'] = strtolower($data['tipo']);
        }

        if (isset($data['slug']) && is_string($data['slug'])) {
            $data['slug'] = strtolower(trim($data['slug']));
            if ($data['slug'] === '') {
                $data['slug'] = null;
            }
        }

        return $data;
    }
}
