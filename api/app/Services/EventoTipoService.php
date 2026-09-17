<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventoTipo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventoTipoService
{
    public function __construct(private readonly IgrejaContext $igrejaContext) {}

    /**
     * @param  array{escopo?: string|null}  $filters
     * @return Collection<int, EventoTipo>
     */
    public function list(array $filters = []): Collection
    {
        $this->ensureDefaults();

        $query = EventoTipo::query()
            ->where('igreja_id', $this->igrejaId())
            ->orderBy('ordem')
            ->orderBy('nome');

        if (! empty($filters['escopo'])) {
            $escopo = $filters['escopo'];
            $query->where(function ($q) use ($escopo): void {
                $q->where('escopo', $escopo)->orWhere('escopo', EventoTipo::ESCOPO_AMBOS);
            });
        }

        return $query->get();
    }

    public function find(string $id): EventoTipo
    {
        return EventoTipo::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EventoTipo
    {
        $codigo = $data['codigo'] ?? EventoTipo::slugifyCodigo($data['nome']);
        $codigo = $this->uniqueCodigo($codigo);

        return EventoTipo::query()->create([
            'igreja_id' => $this->igrejaId(),
            'codigo' => $codigo,
            'nome' => $data['nome'],
            'abrev' => $data['abrev'] ?? mb_substr($data['nome'], 0, 4),
            'cor' => $data['cor'] ?? '#6B1C2B',
            'permite_compras' => (bool) ($data['permite_compras'] ?? false),
            'escopo' => $data['escopo'] ?? EventoTipo::ESCOPO_AMBOS,
            'ordem' => (int) ($data['ordem'] ?? 100),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EventoTipo $tipo, array $data): EventoTipo
    {
        if (isset($data['codigo']) && $data['codigo'] !== $tipo->codigo) {
            $data['codigo'] = $this->uniqueCodigo($data['codigo'], $tipo->id);
        }

        $tipo->fill([
            'codigo' => $data['codigo'] ?? $tipo->codigo,
            'nome' => $data['nome'] ?? $tipo->nome,
            'abrev' => array_key_exists('abrev', $data) ? $data['abrev'] : $tipo->abrev,
            'cor' => array_key_exists('cor', $data) ? $data['cor'] : $tipo->cor,
            'permite_compras' => array_key_exists('permite_compras', $data) ? (bool) $data['permite_compras'] : $tipo->permite_compras,
            'escopo' => $data['escopo'] ?? $tipo->escopo,
            'ordem' => array_key_exists('ordem', $data) ? (int) $data['ordem'] : $tipo->ordem,
        ]);
        $tipo->save();

        return $tipo->refresh();
    }

    public function delete(EventoTipo $tipo): void
    {
        if ($tipo->eventos()->exists()) {
            throw ValidationException::withMessages([
                'tipo' => ['Não é possível excluir tipo com eventos vinculados.'],
            ]);
        }

        $tipo->delete();
    }

    public function ensureDefaults(): void
    {
        $igrejaId = $this->igrejaId();
        if (EventoTipo::query()->where('igreja_id', $igrejaId)->exists()) {
            return;
        }

        DB::transaction(function () use ($igrejaId): void {
            foreach (EventoTipo::defaultsCatalog() as $i => $row) {
                EventoTipo::query()->create([
                    'igreja_id' => $igrejaId,
                    ...$row,
                    'ordem' => $i,
                ]);
            }
        });
    }

    private function uniqueCodigo(string $codigo, ?string $ignoreId = null): string
    {
        $base = $codigo;
        $n = 1;
        while (
            EventoTipo::query()
                ->where('igreja_id', $this->igrejaId())
                ->where('codigo', $codigo)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $codigo = mb_substr($base, 0, 60).'_'.$n;
            $n++;
        }

        return $codigo;
    }

    private function igrejaId(): string
    {
        return $this->igrejaContext->current()->id;
    }
}
