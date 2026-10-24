<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Casal;
use App\Models\EccEquipe;
use App\Models\EccRecado;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class EccRecadoService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly EccVisibilityScope $visibility,
    ) {}

    /**
     * @param  array{status?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $this->assertCanView();

        $query = EccRecado::query()
            ->where('igreja_id', $this->igrejaId())
            ->orderByDesc('fixado')
            ->orderByDesc('urgente')
            ->orderByDesc('publicado_em')
            ->orderByDesc('created_at');

        if ($this->visibility->userCan('ecc.recados.manage')) {
            if (! empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
        } else {
            $this->applyFeedConstraints($query);
        }

        return $query->get()
            ->map(fn (EccRecado $r): array => $this->serialize($r))
            ->values()
            ->all();
    }

    public function novidadesCount(Carbon $desde): int
    {
        $this->assertCanView();

        $query = EccRecado::query()->where('igreja_id', $this->igrejaId());
        $this->applyFeedConstraints($query);
        $query->where('publicado_em', '>', $desde);

        return $query->count();
    }

    public function find(string $id): EccRecado
    {
        $this->assertCanView();

        $recado = EccRecado::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($id)
            ->firstOrFail();

        if (! $this->visibility->userCan('ecc.recados.manage') && ! $this->isVisibleInFeed($recado)) {
            abort(404);
        }

        return $recado;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): EccRecado
    {
        $this->assertCanManage();
        $payload = $this->normalizePayload($data);

        return EccRecado::query()->create([
            ...$payload,
            'igreja_id' => $this->igrejaId(),
            'criado_por_user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EccRecado $recado, array $data): EccRecado
    {
        $this->assertCanManage();
        abort_unless((string) $recado->igreja_id === $this->igrejaId(), 404);

        $recado->fill($this->normalizePayload($data, $recado));
        $recado->save();

        return $recado->refresh();
    }

    public function delete(EccRecado $recado): void
    {
        $this->assertCanManage();
        abort_unless((string) $recado->igreja_id === $this->igrejaId(), 404);
        $recado->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(EccRecado $recado): array
    {
        return [
            'id' => (string) $recado->id,
            'titulo' => (string) $recado->titulo,
            'corpo' => (string) $recado->corpo,
            'urgente' => (bool) $recado->urgente,
            'fixado' => (bool) $recado->fixado,
            'escopo' => (string) $recado->escopo,
            'equipe_id' => $recado->equipe_id ? (string) $recado->equipe_id : null,
            'etapa' => $recado->etapa !== null ? (int) $recado->etapa : null,
            'publicado_em' => $recado->publicado_em?->toIso8601String(),
            'valido_ate' => $recado->valido_ate?->toIso8601String(),
            'status' => (string) $recado->status,
            'criado_por_user_id' => $recado->criado_por_user_id ? (string) $recado->criado_por_user_id : null,
            'created_at' => $recado->created_at?->toIso8601String(),
            'updated_at' => $recado->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Builder<EccRecado>  $query
     */
    private function applyFeedConstraints(Builder $query): void
    {
        $now = now();
        $query->where('status', EccRecado::STATUS_PUBLICADO)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('valido_ate')->orWhere('valido_ate', '>=', $now);
            });

        $equipeIds = $this->visibility->restrictedEquipeIds();
        if ($equipeIds === null) {
            return;
        }

        $etapas = $this->etapasDasEquipes($equipeIds);

        $query->where(function (Builder $q) use ($equipeIds, $etapas): void {
            $q->where('escopo', EccRecado::ESCOPO_PAROQUIA);

            if ($equipeIds !== []) {
                $q->orWhere(function (Builder $inner) use ($equipeIds): void {
                    $inner->where('escopo', EccRecado::ESCOPO_EQUIPE)
                        ->whereIn('equipe_id', $equipeIds);
                });
            }

            if ($etapas !== []) {
                $q->orWhere(function (Builder $inner) use ($etapas): void {
                    $inner->where('escopo', EccRecado::ESCOPO_ETAPA)
                        ->whereIn('etapa', $etapas);
                });
            }
        });
    }

    private function isVisibleInFeed(EccRecado $recado): bool
    {
        if ($recado->status !== EccRecado::STATUS_PUBLICADO) {
            return false;
        }

        if ($recado->valido_ate !== null && $recado->valido_ate->lt(now())) {
            return false;
        }

        $equipeIds = $this->visibility->restrictedEquipeIds();
        if ($equipeIds === null) {
            return true;
        }

        return match ($recado->escopo) {
            EccRecado::ESCOPO_PAROQUIA => true,
            EccRecado::ESCOPO_EQUIPE => $recado->equipe_id !== null
                && in_array((string) $recado->equipe_id, $equipeIds, true),
            EccRecado::ESCOPO_ETAPA => $recado->etapa !== null
                && in_array((int) $recado->etapa, $this->etapasDasEquipes($equipeIds), true),
            default => false,
        };
    }

    /**
     * @param  list<string>  $equipeIds
     * @return list<int>
     */
    private function etapasDasEquipes(array $equipeIds): array
    {
        if ($equipeIds === []) {
            return [];
        }

        return Casal::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereIn('ecc_equipe_id', $equipeIds)
            ->whereHas('etapas')
            ->with('etapas')
            ->get()
            ->flatMap(static fn (Casal $c) => $c->etapas->pluck('etapa'))
            ->map(static fn (mixed $e): int => (int) $e)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, ?EccRecado $existing = null): array
    {
        $escopo = (string) ($data['escopo'] ?? $existing?->escopo ?? EccRecado::ESCOPO_PAROQUIA);
        $status = (string) ($data['status'] ?? $existing?->status ?? EccRecado::STATUS_RASCUNHO);

        $equipeId = array_key_exists('equipe_id', $data) ? $data['equipe_id'] : $existing?->equipe_id;
        $etapa = array_key_exists('etapa', $data) ? $data['etapa'] : $existing?->etapa;

        if ($escopo === EccRecado::ESCOPO_EQUIPE) {
            if ($equipeId === null || $equipeId === '') {
                throw ValidationException::withMessages([
                    'equipe_id' => ['Informe a equipe para escopo equipe.'],
                ]);
            }
            $this->assertEquipeNaIgreja((string) $equipeId);
            $etapa = null;
        } elseif ($escopo === EccRecado::ESCOPO_ETAPA) {
            $etapaInt = $etapa !== null ? (int) $etapa : null;
            if ($etapaInt === null || $etapaInt < 1 || $etapaInt > 3) {
                throw ValidationException::withMessages([
                    'etapa' => ['Informe a etapa (1–3) para escopo etapa.'],
                ]);
            }
            $etapa = $etapaInt;
            $equipeId = null;
        } else {
            $equipeId = null;
            $etapa = null;
        }

        $publicadoEm = array_key_exists('publicado_em', $data)
            ? $this->parseNullableDateTime($data['publicado_em'])
            : $existing?->publicado_em;

        if ($status === EccRecado::STATUS_PUBLICADO && $publicadoEm === null) {
            $publicadoEm = now();
        }

        $validoAte = array_key_exists('valido_ate', $data)
            ? $this->parseNullableDateTime($data['valido_ate'])
            : $existing?->valido_ate;

        return [
            'titulo' => (string) ($data['titulo'] ?? $existing?->titulo),
            'corpo' => (string) ($data['corpo'] ?? $existing?->corpo),
            'urgente' => (bool) ($data['urgente'] ?? $existing?->urgente ?? false),
            'fixado' => (bool) ($data['fixado'] ?? $existing?->fixado ?? false),
            'escopo' => $escopo,
            'equipe_id' => $equipeId ? (string) $equipeId : null,
            'etapa' => $etapa,
            'publicado_em' => $publicadoEm,
            'valido_ate' => $validoAte,
            'status' => $status,
        ];
    }

    private function parseNullableDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function assertEquipeNaIgreja(string $equipeId): void
    {
        $exists = EccEquipe::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($equipeId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'equipe_id' => ['Equipe inválida para a igreja atual.'],
            ]);
        }
    }

    private function assertCanView(): void
    {
        abort_unless(
            $this->visibility->userCan('ecc.recados.view')
                || $this->visibility->userCan('ecc.recados.manage'),
            403,
        );
    }

    private function assertCanManage(): void
    {
        abort_unless($this->visibility->userCan('ecc.recados.manage'), 403);
    }

    private function igrejaId(): string
    {
        return (string) $this->igrejaContext->current()->id;
    }
}
