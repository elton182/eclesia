<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Casal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\EccEvento
 */
class EccEventoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tipo = $this->relationLoaded('eventoTipo') ? $this->eventoTipo : null;

        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'tipo' => $this->tipo,
            'evento_tipo_id' => $this->evento_tipo_id,
            'evento_tipo' => $tipo ? [
                'id' => $tipo->id,
                'codigo' => $tipo->codigo,
                'nome' => $tipo->nome,
                'abrev' => $tipo->abrev,
                'cor' => $tipo->cor,
                'permite_compras' => (bool) $tipo->permite_compras,
                'escopo' => $tipo->escopo,
            ] : null,
            'origem' => $this->origem,
            'permite_compras' => $this->permiteCompras(),
            'inicia_em' => $this->inicia_em?->toIso8601String(),
            'termina_em' => $this->termina_em?->toIso8601String(),
            'local' => $this->local,
            'casal_compras_id' => $this->casal_compras_id,
            'casal_compras_rotulo' => $this->whenLoaded('casalCompras', fn () => $this->casalRotulo($this->casalCompras)),
            'evento_agenda_id' => $this->evento_agenda_id,
            'participantes_count' => $this->casais_count ?? $this->casais->count(),
            'convidados_total' => $this->whenLoaded('casais', function () {
                return (int) $this->casais->sum(fn (Casal $c) => (int) ($c->pivot->convidados ?? 0));
            }),
            'participantes' => $this->whenLoaded('casais', function () {
                return $this->casais->map(fn (Casal $c) => [
                    'casal_id' => $c->id,
                    'casal_rotulo' => $this->casalRotulo($c),
                    'convidados' => (int) ($c->pivot->convidados ?? 0),
                ])->values()->all();
            }),
            'itens_compra' => EccItemCompraResource::collection($this->whenLoaded('itensCompra')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function casalRotulo(?Casal $casal): ?string
    {
        if ($casal === null) {
            return null;
        }

        $a = $casal->pessoaA?->nome ?? '';
        $b = $casal->pessoaB?->nome ?? '';

        return trim($a.' e '.$b, ' e') ?: null;
    }
}
