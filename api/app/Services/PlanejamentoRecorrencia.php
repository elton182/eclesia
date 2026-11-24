<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Expande data_inicio (+ data_fim) em ocorrências até o fim do ano do planejamento (SPEC-038).
 * Não persiste RRULE: cada item vira um PlanejamentoEvento separado.
 */
final class PlanejamentoRecorrencia
{
    public const MODO_SEMANAL = 'semanal';

    public const MODO_MENSAL = 'mensal';

    public const MODOS = [self::MODO_SEMANAL, self::MODO_MENSAL];

    /**
     * @return list<array{data_inicio: string, data_fim: ?string}>
     */
    public static function expandir(
        string $dataInicio,
        ?string $dataFim,
        ?string $modo,
        int $ano,
    ): array {
        $inicio = Carbon::parse($dataInicio)->startOfDay();
        $fim = $dataFim !== null && $dataFim !== ''
            ? Carbon::parse($dataFim)->startOfDay()
            : null;

        if ($fim !== null && $fim->lt($inicio)) {
            throw new InvalidArgumentException('data_fim anterior a data_inicio');
        }

        $duracaoDias = $fim !== null ? (int) $inicio->diffInDays($fim) : null;

        if ($modo === null || $modo === '') {
            return [self::ocorrencia($inicio, $duracaoDias)];
        }

        if (! in_array($modo, self::MODOS, true)) {
            throw new InvalidArgumentException('modo de repetição inválido');
        }

        $limite = Carbon::create($ano, 12, 31)->startOfDay();
        $datas = $modo === self::MODO_SEMANAL
            ? self::datasSemanais($inicio, $limite, $ano)
            : self::datasMensais($inicio, $limite, $ano);

        if ($datas === []) {
            return [self::ocorrencia($inicio, $duracaoDias)];
        }

        return array_map(
            static fn (Carbon $d) => self::ocorrencia($d, $duracaoDias),
            $datas,
        );
    }

    /**
     * @return list<Carbon>
     */
    private static function datasSemanais(Carbon $inicio, Carbon $limite, int $ano): array
    {
        $datas = [];
        $cursor = $inicio->copy();
        while ($cursor->lte($limite) && (int) $cursor->year === $ano) {
            $datas[] = $cursor->copy();
            $cursor->addWeek();
        }

        return $datas;
    }

    /**
     * @return list<Carbon>
     */
    private static function datasMensais(Carbon $inicio, Carbon $limite, int $ano): array
    {
        $datas = [];
        $diaAlvo = (int) $inicio->day;
        $cursor = $inicio->copy()->startOfMonth();

        while ((int) $cursor->year === $ano && $cursor->lte($limite)) {
            $ultimoDia = (int) $cursor->copy()->endOfMonth()->day;
            $dia = min($diaAlvo, $ultimoDia);
            $data = $cursor->copy()->day($dia)->startOfDay();
            if ($data->gte($inicio) && $data->lte($limite) && (int) $data->year === $ano) {
                $datas[] = $data;
            }
            $cursor->addMonthNoOverflow();
        }

        return $datas;
    }

    /**
     * @return array{data_inicio: string, data_fim: ?string}
     */
    private static function ocorrencia(Carbon $inicio, ?int $duracaoDias): array
    {
        $dataInicio = $inicio->format('Y-m-d');
        if ($duracaoDias === null) {
            return ['data_inicio' => $dataInicio, 'data_fim' => null];
        }

        return [
            'data_inicio' => $dataInicio,
            'data_fim' => $inicio->copy()->addDays($duracaoDias)->format('Y-m-d'),
        ];
    }
}
