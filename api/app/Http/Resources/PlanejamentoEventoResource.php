<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PlanejamentoEvento
 */
class PlanejamentoEventoResource extends JsonResource
{
    /** @var list<array<string, mixed>> */
    private array $conflitos = [];

    /**
     * @param  list<array<string, mixed>>  $conflitos
     */
    public function withConflitos(array $conflitos): self
    {
        $this->conflitos = $conflitos;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $horaInicio = $this->hora_inicio;
        $horaFim = $this->hora_fim;
        if ($horaInicio instanceof \DateTimeInterface) {
            $horaInicio = $horaInicio->format('H:i');
        } elseif (is_string($horaInicio) && preg_match('/^(\d{2}:\d{2})/', $horaInicio, $m)) {
            $horaInicio = $m[1];
        }
        if ($horaFim instanceof \DateTimeInterface) {
            $horaFim = $horaFim->format('H:i');
        } elseif (is_string($horaFim) && preg_match('/^(\d{2}:\d{2})/', $horaFim, $m)) {
            $horaFim = $m[1];
        }

        return [
            'id' => $this->id,
            'planejamento_anual_id' => $this->planejamento_anual_id,
            'pastoral_id' => $this->pastoral_id,
            'pastoral_nome' => $this->whenLoaded('pastoral', fn () => $this->pastoral?->nome),
            'titulo' => $this->titulo,
            'data_inicio' => $this->data_inicio?->format('Y-m-d'),
            'data_fim' => $this->data_fim?->format('Y-m-d'),
            'hora_inicio' => $horaInicio,
            'hora_fim' => $horaFim,
            'participantes_media' => $this->participantes_media,
            'recorrencia_texto' => $this->recorrencia_texto,
            'observacoes' => $this->observacoes,
            'local_texto' => $this->local_texto,
            'local_ids' => $this->whenLoaded('locais', fn () => $this->locais->pluck('id')->values()->all()),
            'locais' => $this->whenLoaded('locais', fn () => CalendarioLocalResource::collection($this->locais)),
            'status_solicitacao' => $this->status_solicitacao,
            'motivo_ajuste' => $this->motivo_ajuste,
            'conflitos' => $this->conflitos,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
