<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CalendarioItem;
use App\Models\CalendarioLocal;
use App\Services\CalendarioService;
use App\Services\IgrejaContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class CalendarioOficialPdfLayoutTest extends TestCase
{
    public function test_view_ordem_secoes_formato_oficial(): void
    {
        $mensal = new class
        {
            public string $titulo = 'Paróquia Teste';

            public ?string $subtitulo = null;

            public int $ano = 2026;

            public object $igreja;

            public function __construct()
            {
                $this->igreja = (object) ['nome' => 'Paróquia Teste'];
            }
        };

        $html = view('calendario.oficial-pdf', [
            'mensal' => $mensal,
            'mesNome' => 'SETEMBRO',
            'logoDioceseSrc' => null,
            'logoParoquiaSrc' => null,
            'localidadeLinha' => 'Jundiaí / Diocese de Jundiaí – SP',
            'corPrimary' => '#4E1220',
            'corSecondary' => '#C88A5E',
            'corTexto' => '#2A1418',
            'corMuted' => '#6B4A50',
            'corOnPrimary' => '#FFFFFF',
            'corMap' => ['pe. rodolfo' => '#1E5AA8'],
            'temposLiturgicosLabels' => [
                '23º Domingo do Tempo Comum',
                '24º Domingo do Tempo Comum',
            ],
            'observacoes' => collect([
                (object) ['titulo' => '1º final de semana', 'descricao' => "Missa com crianças\nHorário especial"],
                (object) ['titulo' => '4º final de semana', 'descricao' => 'Missa do ECC'],
            ]),
            'gradeSemana' => [
                [
                    'diaLabel' => 'SEGUNDA',
                    'datas' => ['07', '14', '21', '28'],
                    'linhas' => [],
                ],
                [
                    'diaLabel' => 'TERÇA',
                    'datas' => ['01', '08', '15', '22', '29'],
                    'linhas' => [[
                        'local' => 'MATRIZ',
                        'hora' => '07H',
                        'celulas' => [
                            ['texto' => 'Pe. Rodolfo', 'cor' => '#1E5AA8', 'ref' => null, 'cancelado' => false],
                            ['texto' => 'Não haverá', 'cor' => null, 'ref' => null, 'cancelado' => true],
                            ['texto' => '-', 'cor' => null, 'ref' => null, 'cancelado' => false],
                            ['texto' => '-', 'cor' => null, 'ref' => null, 'cancelado' => false],
                            ['texto' => '-', 'cor' => null, 'ref' => null, 'cancelado' => false],
                        ],
                    ]],
                ],
                ['diaLabel' => 'QUARTA', 'datas' => ['02', '09', '16', '23', '30'], 'linhas' => []],
                ['diaLabel' => 'QUINTA', 'datas' => ['03', '10', '17', '24'], 'linhas' => []],
                ['diaLabel' => 'SEXTA', 'datas' => ['04', '11', '18', '25'], 'linhas' => []],
            ],
            'gradeFds' => [[
                'diaLabel' => 'SÁBADO',
                'datas' => ['05', '12'],
                'linhas' => [[
                    'local' => 'MATRIZ',
                    'hora' => '08H',
                    'celulas' => [
                        ['texto' => 'Pe. Marcos', 'cor' => '#3D6B2F', 'ref' => '2', 'cancelado' => false],
                        ['texto' => '-', 'cor' => null, 'ref' => null, 'cancelado' => false],
                    ],
                ]],
            ]],
            'festas' => collect([(object) [
                'data' => Carbon::parse('2026-09-11'),
                'hora' => '19:30:00',
                'titulo' => 'Festa de Santa Cruz',
                'notas' => null,
                'celebrante_nome' => 'Pe. Rodolfo',
                'local' => (object) ['nome' => 'Santa Cruz'],
                'tipo' => (object) ['nome' => 'Festa'],
            ]]),
            'obsMoveis' => collect([(object) [
                'data' => Carbon::parse('2026-09-04'),
                'hora' => '18:30:00',
                'titulo' => 'Adoração',
                'notas' => null,
                'celebrante_nome' => 'Sem. Paulo',
            ]]),
            'casamentos' => collect([(object) [
                'data' => Carbon::parse('2026-09-05'),
                'hora' => '16:00:00',
                'celebrante_nome' => 'Diác. Dirceu',
                'local' => (object) ['nome' => 'Matriz'],
            ]]),
        ])->render();

        $this->assertStringContainsString('Jundiaí / Diocese de Jundiaí – SP', $html);
        $this->assertStringContainsString('header-titulo', $html);
        $this->assertStringContainsString('SEGUNDA', $html);
        $this->assertStringContainsString('SEXTA', $html);
        $this->assertStringContainsString('CELEBRAÇÕES NA SEMANA', $html);
        $this->assertStringContainsString('CELEBRAÇÕES NOS SÁBADOS E DOMINGOS', $html);
        $this->assertStringContainsString('Tempo Litúrgico', $html);
        $this->assertStringContainsString('23º Domingo do Tempo Comum', $html);
        $this->assertStringContainsString('FESTA DE SANTA CRUZ', $html);
        $this->assertStringContainsString('*Observações móveis:', $html);
        $this->assertStringContainsString('Casamentos:', $html);
        $this->assertStringContainsString('Observações fixas:', $html);
        $this->assertStringContainsString('1º final de semana', $html);
        $this->assertStringContainsString('Missa com crianças', $html);
        $this->assertStringContainsString('Horário especial', $html);
        $this->assertStringContainsString('<br', $html);
        $this->assertStringContainsString('4º final de semana', $html);
        $this->assertStringContainsString('Missa do ECC', $html);
        $this->assertStringNotContainsString('white-space: pre-wrap', $html);
        $this->assertStringNotContainsString('obs-fixas', $html);
        $this->assertMatchesRegularExpression(
            '/Observações fixas:<\/div>\s*<ul class="lista lista-obs">\s*<li>\s*<strong>1\) 1º final de semana:<\/strong>\s*<span class="obs-desc">Missa com crianças<br\s*\/?>\s*Horário especial<\/span>\s*<\/li>/',
            $html,
        );
        $this->assertStringContainsString('.lista-obs li', $html);
        $this->assertStringContainsString('às 19H30', $html);
        $this->assertStringContainsString('às 18H30', $html);
        $this->assertStringContainsString('às 16H', $html);
        $this->assertStringContainsString('#4E1220', $html);

        $posSemana = strpos($html, 'CELEBRAÇÕES NA SEMANA');
        $posFds = strpos($html, 'CELEBRAÇÕES NOS SÁBADOS E DOMINGOS');
        $posFesta = strpos($html, 'FESTA DE SANTA CRUZ');
        $posObs = strpos($html, '*Observações móveis:');
        $posCas = strpos($html, 'Casamentos:');
        $posFix = strpos($html, 'Observações fixas:');

        $this->assertNotFalse($posSemana);
        $this->assertTrue($posSemana < $posFds);
        $this->assertTrue($posFds < $posFesta);
        $this->assertTrue($posFesta < $posObs);
        $this->assertTrue($posObs < $posCas);
        $this->assertTrue($posCas < $posFix);
    }

    public function test_format_hora_pdf_oficial(): void
    {
        $this->assertSame('07H', CalendarioService::formatHoraPdf('07:00:00'));
        $this->assertSame('19H30', CalendarioService::formatHoraPdf('19:30:00'));
        $this->assertSame('08H', CalendarioService::formatHoraPdf('08:00'));
        $this->assertSame('—', CalendarioService::formatHoraPdf(null));
        $this->assertSame('—', CalendarioService::formatHoraPdf(''));
    }

    public function test_grade_semana_imprime_mes_todo_mesmo_sem_itens(): void
    {
        $local = new CalendarioLocal(['nome' => 'Matriz', 'ordem' => 1]);
        $local->id = 'loc1';

        $itens = new Collection([
            $this->fakeItem('semana', '2026-09-01', '07:00:00', $local, 'Pe. Rodolfo'),
            $this->fakeItem('semana', '2026-09-08', '07:00:00', $local, 'Pe. Marcos'),
        ]);

        $service = new CalendarioService(new IgrejaContext);
        $method = new ReflectionMethod(CalendarioService::class, 'pdfGradeSemana');
        $method->setAccessible(true);
        /** @var list<array<string, mixed>> $grade */
        $grade = $method->invoke($service, $itens, [
            'pe. rodolfo' => '#1E5AA8',
            'pe. marcos' => '#3D6B2F',
        ], 2026, 9);

        $this->assertCount(5, $grade);
        $this->assertSame('SEGUNDA', $grade[0]['diaLabel']);
        $this->assertSame(['07', '14', '21', '28'], $grade[0]['datas']);
        $this->assertSame([], $grade[0]['linhas']);

        $this->assertSame('TERÇA', $grade[1]['diaLabel']);
        $this->assertSame(['01', '08', '15', '22', '29'], $grade[1]['datas']);
        $this->assertSame('Pe. Rodolfo', $grade[1]['linhas'][0]['celulas'][0]['texto']);
        $this->assertSame('Pe. Marcos', $grade[1]['linhas'][0]['celulas'][1]['texto']);
        $this->assertSame('-', $grade[1]['linhas'][0]['celulas'][2]['texto']);
        $this->assertSame('SEXTA', $grade[4]['diaLabel']);
    }

    public function test_grade_ordena_linhas_por_horario(): void
    {
        $matriz = new CalendarioLocal(['nome' => 'Matriz', 'ordem' => 1]);
        $matriz->id = 'loc-matriz';
        $cemiterio = new CalendarioLocal(['nome' => 'Cemitério', 'ordem' => 2]);
        $cemiterio->id = 'loc-cem';

        // Ordem de inserção propositalmente fora de hora
        $itens = new Collection([
            $this->fakeItem('fds', '2026-09-05', '17:00:00', $matriz, 'Pe. Marcos'),
            $this->fakeItem('fds', '2026-09-05', '08:00:00', $cemiterio, 'Pe. Rodolfo'),
            $this->fakeItem('fds', '2026-09-05', '18:30:00', $matriz, 'Pe. Fábio'),
        ]);

        $service = new CalendarioService(new IgrejaContext);
        $method = new ReflectionMethod(CalendarioService::class, 'pdfGradeFds');
        $method->setAccessible(true);
        /** @var list<array<string, mixed>> $grade */
        $grade = $method->invoke($service, $itens, [], 2026, 9);

        $this->assertSame('SÁBADO', $grade[0]['diaLabel']);
        $horas = array_column($grade[0]['linhas'], 'hora');
        $this->assertSame(['08H', '17H', '18H30'], $horas);
        $this->assertSame('CEMITÉRIO', $grade[0]['linhas'][0]['local']);
        $this->assertSame('MATRIZ', $grade[0]['linhas'][1]['local']);
        $this->assertSame('MATRIZ', $grade[0]['linhas'][2]['local']);
    }

    private function fakeItem(
        string $secao,
        string $data,
        string $hora,
        CalendarioLocal $local,
        string $celebrante,
    ): CalendarioItem {
        $item = new CalendarioItem([
            'secao' => $secao,
            'data' => $data,
            'hora' => $hora,
            'celebrante_nome' => $celebrante,
            'local_id' => $local->id,
        ]);
        $item->setRelation('local', $local);

        return $item;
    }
}
