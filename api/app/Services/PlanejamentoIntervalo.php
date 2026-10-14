<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanejamentoEvento;
use Carbon\Carbon;

/**
 * Intervalo [inicio, fim) de um evento do planejamento.
 * Sem hora = dia inteiro; só hora_inicio em evento de um dia = 1h.
 */
final class PlanejamentoIntervalo
{
    private function __construct(
        public readonly Carbon $inicio,
        public readonly Carbon $fim,
    ) {}

    public static function deEvento(PlanejamentoEvento $evento): self
    {
        return self::deDados(
            $evento->data_inicio->format('Y-m-d'),
            $evento->data_fim?->format('Y-m-d'),
            self::formatHora($evento->hora_inicio),
            self::formatHora($evento->hora_fim),
        );
    }

    public static function deDados(string $dataInicio, ?string $dataFim, ?string $horaInicio, ?string $horaFim): self
    {
        $dataInicio = substr($dataInicio, 0, 10);
        $dataFim = $dataFim !== null && $dataFim !== '' ? substr($dataFim, 0, 10) : null;
        $horaInicio = self::formatHora($horaInicio);
        $horaFim = self::formatHora($horaFim);

        $inicio = Carbon::parse($dataInicio.' '.($horaInicio ?? '00:00').':00');

        if ($horaFim === null && $horaInicio !== null && $dataFim === null) {
            return new self($inicio, $inicio->copy()->addHour());
        }

        $fim = Carbon::parse(($dataFim ?? $dataInicio).' '.($horaFim ?? $horaInicio ?? '23:59').':00');

        return new self($inicio, $fim);
    }

    public function sobrepoe(self $outro): bool
    {
        return $this->inicio < $outro->fim && $outro->inicio < $this->fim;
    }

    public static function formatHora(mixed $hora): ?string
    {
        if ($hora === null || $hora === '') {
            return null;
        }

        if ($hora instanceof \DateTimeInterface) {
            return $hora->format('H:i');
        }

        $str = (string) $hora;
        if (preg_match('/^(\d{2}:\d{2})/', $str, $m)) {
            return $m[1];
        }

        return $str;
    }
}
