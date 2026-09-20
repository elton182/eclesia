<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EccCasalImportParser;
use PHPUnit\Framework\TestCase;

class EccCasalImportParserTest extends TestCase
{
    private EccCasalImportParser $parser;

    /** @var list<array{id: string, nome: string, slug: string}> */
    private array $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new EccCasalImportParser;
        $this->catalog = [
            ['id' => 'eq-cozinha', 'nome' => 'Cozinha', 'slug' => 'cozinha'],
            ['id' => 'eq-cafe', 'nome' => 'Café e Minimercado', 'slug' => 'cafe-e-minimercado'],
            ['id' => 'eq-sala', 'nome' => 'Sala', 'slug' => 'sala'],
            ['id' => 'eq-ordem', 'nome' => 'Ordem e Limpeza', 'slug' => 'ordem-e-limpeza'],
            ['id' => 'eq-acolhida', 'nome' => 'Acolhida', 'slug' => 'acolhida'],
            ['id' => 'eq-visitacao', 'nome' => 'Visitação', 'slug' => 'visitacao'],
            ['id' => 'eq-liturgia', 'nome' => 'Liturgia/Vigília', 'slug' => 'liturgia-vigilia'],
            ['id' => 'eq-circulos', 'nome' => 'Círculos', 'slug' => 'circulos'],
            ['id' => 'eq-compras', 'nome' => 'Compras', 'slug' => 'compras'],
            ['id' => 'eq-secretaria', 'nome' => 'Secretaria', 'slug' => 'secretaria'],
            ['id' => 'eq-coord', 'nome' => 'Coordenação Geral', 'slug' => 'coordenacao-geral'],
            ['id' => 'eq-palestras', 'nome' => 'Palestras', 'slug' => 'palestras'],
        ];
    }

    public function test_parse_etapas_e_preferencias_da_planilha(): void
    {
        $row = [
            'Qual ECC vocês fizeram? ' => '8º',
            'Tem 2ª Etapa' => '11º',
            'Tem 3ª Etapa' => 'Não',
            'Em qual função você gostaria de trabalhar?' => 'Cozinha/Café ( Dentre as opções pode ser Cord. Café)',
        ];
        $get = $this->getter($row);

        $enriched = $this->parser->enrichFromSpreadsheetRow($row, $this->catalog, $get);

        $this->assertSame([
            ['etapa' => 1, 'ecc_numero' => '8º', 'data' => null, 'local' => null],
            ['etapa' => 2, 'ecc_numero' => '11º', 'data' => null, 'local' => null],
        ], $enriched['etapas']);

        $prefIds = array_column($enriched['preferencias'], 'equipe_servico_id');
        $this->assertSame(['eq-cozinha', 'eq-cafe'], $prefIds);
        $this->assertSame(1, $enriched['preferencias'][0]['ordem']);
    }

    public function test_parse_historico_coordenou_e_indicacoes_por_ano(): void
    {
        $row = [
            'Já trabalharam no encontro do ECC?' => 'Sim, Cordenou Cozinha, Cordenou ordem , Café ,Sala foi Casal Boa Vontade,',
            '2023 CONVITE PARA:' => 'Sala: Testemunho',
            'ACEITOU?' => 'Não',
            'Indicação para 2024' => '',
            'Função' => 'Cordenou Cozinha',
            'Aceitou' => 'Sim',
            'Indicação para 2025' => 'Acolhida',
            'Função_1' => '',
            'Aceitou_1' => '',
        ];
        $get = $this->getter($row);

        $enriched = $this->parser->enrichFromSpreadsheetRow($row, $this->catalog, $get);
        $byKey = [];
        foreach ($enriched['atividades'] as $a) {
            $byKey[$a['ecc_numero'].'|'.$a['equipe_servico_id']] = $a;
        }

        $this->assertSame('C', $byKey['hist|eq-cozinha']['status']);
        $this->assertSame('C', $byKey['hist|eq-ordem']['status']);
        $this->assertSame('A', $byKey['hist|eq-cafe']['status']);
        $this->assertSame('A', $byKey['hist|eq-sala']['status']);

        $this->assertSame('N', $byKey['2023|eq-sala']['status']);
        $this->assertSame('C', $byKey['2024|eq-cozinha']['status']);
        $this->assertSame('IC', $byKey['2025|eq-acolhida']['status']);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return callable(list<string>): mixed
     */
    private function getter(array $row): callable
    {
        return function (array $keys) use ($row): mixed {
            $normalizedKeys = array_map(fn (string $k) => $this->norm($k), $keys);
            foreach ($row as $header => $value) {
                if (in_array($this->norm((string) $header), $normalizedKeys, true)) {
                    return is_string($value) ? trim($value) : $value;
                }
            }

            return null;
        };
    }

    private function norm(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = str_replace(['º', 'ª', '°'], '', $header);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
        if (is_string($transliterated) && $transliterated !== '') {
            $header = $transliterated;
        }
        $header = preg_replace('/[^a-z0-9 ?_-]+/u', '', $header) ?? $header;
        $header = preg_replace('/\s+/u', ' ', $header) ?? $header;

        return trim($header, " \t\n\r\0\x0B:?_-");
    }
}
