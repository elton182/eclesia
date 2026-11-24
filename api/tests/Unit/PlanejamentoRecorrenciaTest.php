<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PlanejamentoRecorrencia;
use Tests\TestCase;

class PlanejamentoRecorrenciaTest extends TestCase
{
    public function test_sem_modo_retorna_uma_ocorrencia(): void
    {
        $lista = PlanejamentoRecorrencia::expandir('2026-04-15', null, null, 2026);
        $this->assertSame([
            ['data_inicio' => '2026-04-15', 'data_fim' => null],
        ], $lista);
    }

    public function test_semanal_mesmo_weekday_ate_fim_do_ano(): void
    {
        // 2026-04-06 é segunda
        $lista = PlanejamentoRecorrencia::expandir('2026-04-06', null, 'semanal', 2026);
        $this->assertSame('2026-04-06', $lista[0]['data_inicio']);
        $this->assertSame('2026-12-28', $lista[array_key_last($lista)]['data_inicio']);
        foreach ($lista as $oc) {
            $this->assertSame(1, (int) date('N', strtotime($oc['data_inicio'])));
        }
        $this->assertCount(39, $lista);
    }

    public function test_mensal_mesmo_dia_ate_dezembro(): void
    {
        $lista = PlanejamentoRecorrencia::expandir('2026-04-15', null, 'mensal', 2026);
        $datas = array_column($lista, 'data_inicio');
        $this->assertSame([
            '2026-04-15',
            '2026-05-15',
            '2026-06-15',
            '2026-07-15',
            '2026-08-15',
            '2026-09-15',
            '2026-10-15',
            '2026-11-15',
            '2026-12-15',
        ], $datas);
    }

    public function test_mensal_dia_31_usa_ultimo_dia_em_fevereiro(): void
    {
        $lista = PlanejamentoRecorrencia::expandir('2026-01-31', null, 'mensal', 2026);
        $porMes = [];
        foreach ($lista as $oc) {
            $porMes[(int) substr($oc['data_inicio'], 5, 2)] = $oc['data_inicio'];
        }
        $this->assertSame('2026-01-31', $porMes[1]);
        $this->assertSame('2026-02-28', $porMes[2]);
        $this->assertSame('2026-03-31', $porMes[3]);
        $this->assertSame('2026-04-30', $porMes[4]);
        $this->assertCount(12, $lista);
    }

    public function test_preserva_duracao_data_fim(): void
    {
        $lista = PlanejamentoRecorrencia::expandir('2026-04-06', '2026-04-08', 'semanal', 2026);
        $this->assertSame('2026-04-06', $lista[0]['data_inicio']);
        $this->assertSame('2026-04-08', $lista[0]['data_fim']);
        $this->assertSame('2026-04-13', $lista[1]['data_inicio']);
        $this->assertSame('2026-04-15', $lista[1]['data_fim']);
    }
}
