<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Heurísticas para enriquecer a importação da planilha "EQUIPES ATIVAS ECC"
 * com etapas, atividades e preferências estruturadas (SPEC-015).
 */
class EccCasalImportParser
{
    /**
     * Aliases livres da planilha → slug do catálogo de equipes de serviço.
     *
     * @var array<string, string>
     */
    private const EQUIPE_ALIASES = [
        'coordenacao geral' => 'coordenacao-geral',
        'cordenador geral' => 'coordenacao-geral',
        'coordenador geral' => 'coordenacao-geral',
        'cord geral' => 'coordenacao-geral',
        'sala' => 'sala',
        'liturgia' => 'liturgia-vigilia',
        'vigilia' => 'liturgia-vigilia',
        'liturgia vigilia' => 'liturgia-vigilia',
        'circulos' => 'circulos',
        'circulo' => 'circulos',
        'cafe' => 'cafe-e-minimercado',
        'cafe e minimercado' => 'cafe-e-minimercado',
        'minimercado' => 'cafe-e-minimercado',
        'cozinha' => 'cozinha',
        'ordem' => 'ordem-e-limpeza',
        'ordem e limpeza' => 'ordem-e-limpeza',
        'ordem limpeza' => 'ordem-e-limpeza',
        'visitacao' => 'visitacao',
        'acolhida' => 'acolhida',
        'secretaria' => 'secretaria',
        'secretria' => 'secretaria',
        'compras' => 'compras',
        'palestras' => 'palestras',
        'palestra' => 'palestras',
    ];

    /**
     * @param  list<array{id: string, nome: string, slug: string}>  $catalog
     * @return array{
     *   etapas: list<array{etapa: int, ecc_numero: ?string, data: null, local: null}>,
     *   atividades: list<array{ecc_numero: string, equipe_servico_id: string, status: string, observacao: ?string}>,
     *   preferencias: list<array{equipe_servico_id: string, ordem: int}>
     * }
     */
    public function enrichFromSpreadsheetRow(array $row, array $catalog, callable $get): array
    {
        $bySlug = [];
        foreach ($catalog as $eq) {
            $bySlug[$eq['slug']] = $eq['id'];
        }

        $etapas = $this->parseEtapas($get);
        $preferencias = $this->parsePreferencias(
            (string) ($get([
                'em qual função você gostaria de trabalhar?',
                'em qual funcao voce gostaria de trabalhar?',
            ]) ?? ''),
            $bySlug,
        );

        $atividades = [];
        $atividades = $this->mergeAtividades(
            $atividades,
            $this->parseHistoricoServico(
                (string) ($get([
                    'já trabalharam no encontro do ecc?',
                    'ja trabalharam no encontro do ecc?',
                ]) ?? ''),
                $bySlug,
            ),
        );
        $atividades = $this->mergeAtividades(
            $atividades,
            $this->parseConviteAno(
                '2023',
                (string) ($this->cellExact($row, ['2023 CONVITE PARA:', '2023 CONVITE PARA']) ?? ''),
                (string) ($this->cellExact($row, ['ACEITOU?', 'Aceitou?']) ?? ''),
                $bySlug,
            ),
        );
        $atividades = $this->mergeAtividades(
            $atividades,
            $this->parseIndicacaoAno(
                '2024',
                (string) ($this->cellExact($row, ['Indicação para 2024', 'Indicacao para 2024']) ?? ''),
                (string) ($this->cellExact($row, ['Função']) ?? ''), // não Função_1
                (string) ($this->cellExact($row, ['Aceitou']) ?? ''), // não Aceitou_1 / ACEITOU?
                $bySlug,
            ),
        );
        $atividades = $this->mergeAtividades(
            $atividades,
            $this->parseIndicacaoAno(
                '2025',
                (string) ($this->cellExact($row, ['Indicação para 2025', 'Indicacao para 2025']) ?? ''),
                (string) ($this->cellExact($row, ['Função_1', 'Funcao_1']) ?? ''),
                (string) ($this->cellExact($row, ['Aceitou_1']) ?? ''),
                $bySlug,
            ),
        );

        return [
            'etapas' => $etapas,
            'atividades' => array_values($atividades),
            'preferencias' => $preferencias,
        ];
    }

    /**
     * Match exato do cabeçalho (case-insensitive), para colunas duplicadas da planilha
     * (ACEITOU? vs Aceitou vs Aceitou_1).
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $headers
     */
    private function cellExact(array $row, array $headers): mixed
    {
        $wanted = array_map(static fn (string $h) => mb_strtolower(trim($h)), $headers);

        foreach ($row as $header => $value) {
            $h = mb_strtolower(trim((string) $header));
            if (in_array($h, $wanted, true)) {
                if (is_string($value)) {
                    return trim($value);
                }

                return $value;
            }
        }

        return null;
    }

    /**
     * @param  callable(list<string>): mixed  $get
     * @return list<array{etapa: int, ecc_numero: ?string, data: null, local: null}>
     */
    private function parseEtapas(callable $get): array
    {
        $etapas = [];
        $origem = $this->normalizeEccNumero($get([
            'qual ecc vocês fizeram?',
            'qual ecc voces fizeram?',
            'ecc_origem',
        ]));
        if ($origem !== null) {
            $etapas[] = ['etapa' => 1, 'ecc_numero' => $origem, 'data' => null, 'local' => null];
        }

        $e2 = $this->normalizeEccNumero($get(['tem 2ª etapa', 'tem 2a etapa']));
        if ($e2 !== null) {
            $etapas[] = ['etapa' => 2, 'ecc_numero' => $e2, 'data' => null, 'local' => null];
        }

        $e3 = $this->normalizeEccNumero($get(['tem 3ª etapa', 'tem 3a etapa']));
        if ($e3 !== null) {
            $etapas[] = ['etapa' => 3, 'ecc_numero' => $e3, 'data' => null, 'local' => null];
        }

        return $etapas;
    }

    private function normalizeEccNumero(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $lower = mb_strtolower($raw);
        if (in_array($lower, ['nao', 'não', 'n', 'no', '-', 'x'], true)) {
            return null;
        }

        return $raw;
    }

    /**
     * @param  array<string, string>  $bySlug
     * @return list<array{equipe_servico_id: string, ordem: int}>
     */
    private function parsePreferencias(string $text, array $bySlug): array
    {
        $text = trim($text);
        if ($text === '' || preg_match('/nao preencheu|não preencheu|sem prefer/iu', $text) === 1) {
            return [];
        }

        // Remove cláusulas entre parênteses (ex.: "Dentre as opções pode ser Cord. Café")
        $clean = preg_replace('/\([^)]*\)/u', ' ', $text) ?? $text;
        $ids = $this->extractEquipeIds($clean, $bySlug);
        $out = [];
        $ordem = 1;
        foreach ($ids as $id) {
            $out[] = ['equipe_servico_id' => $id, 'ordem' => $ordem++];
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $bySlug
     * @return list<array{ecc_numero: string, equipe_servico_id: string, status: string, observacao: ?string}>
     */
    private function parseHistoricoServico(string $text, array $bySlug): array
    {
        $text = trim($text);
        if ($text === '' || preg_match('/^nao\b|^não\b/iu', $text) === 1) {
            return [];
        }

        $out = [];
        // Segmentos separados por vírgula
        $parts = preg_split('/[,;]+/u', $text) ?: [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || preg_match('/^sim\b/iu', $part) === 1 && mb_strlen($part) < 6) {
                continue;
            }
            $status = $this->detectStatusInSegment($part) ?? 'A';
            foreach ($this->extractEquipeIds($part, $bySlug) as $id) {
                $out[] = [
                    'ecc_numero' => 'hist',
                    'equipe_servico_id' => $id,
                    'status' => $status,
                    'observacao' => null,
                ];
            }
        }

        // Se nada casou por segmento, tenta o texto inteiro
        if ($out === []) {
            $status = str_contains(mb_strtolower($text), 'corden') || str_contains(mb_strtolower($text), 'coorden')
                ? 'C'
                : 'A';
            foreach ($this->extractEquipeIds($text, $bySlug) as $id) {
                $out[] = [
                    'ecc_numero' => 'hist',
                    'equipe_servico_id' => $id,
                    'status' => $status,
                    'observacao' => null,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $bySlug
     * @return list<array{ecc_numero: string, equipe_servico_id: string, status: string, observacao: ?string}>
     */
    private function parseConviteAno(string $ano, string $convite, string $aceitou, array $bySlug): array
    {
        $convite = trim($convite);
        if ($convite === '') {
            return [];
        }

        $status = $this->parseAceiteStatus($aceitou) ?? 'IC';
        // "Não/Sim" com múltiplas equipes: aplica o primeiro token por padrão
        if (str_contains($aceitou, '/')) {
            $status = $this->parseAceiteStatus(explode('/', $aceitou)[0]) ?? $status;
        }

        $ids = $this->extractEquipeIds($convite, $bySlug);
        $obs = $convite;
        $out = [];
        foreach ($ids as $id) {
            $out[] = [
                'ecc_numero' => $ano,
                'equipe_servico_id' => $id,
                'status' => $status,
                'observacao' => $obs,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $bySlug
     * @return list<array{ecc_numero: string, equipe_servico_id: string, status: string, observacao: ?string}>
     */
    private function parseIndicacaoAno(
        string $ano,
        string $indicacao,
        string $funcao,
        string $aceitou,
        array $bySlug,
    ): array {
        $indicacao = trim($indicacao);
        $funcao = trim($funcao);
        if ($indicacao === '' && $funcao === '') {
            return [];
        }

        $source = $funcao !== '' ? $funcao : $indicacao;
        $ids = $this->extractEquipeIds($source, $bySlug);
        if ($ids === [] && $indicacao !== '') {
            $ids = $this->extractEquipeIds($indicacao, $bySlug);
        }

        $statusFromFuncao = $this->detectStatusInSegment($funcao);
        $statusFromAceite = $this->parseAceiteStatus($aceitou);
        $status = $statusFromFuncao
            ?? $statusFromAceite
            ?? ($aceitou === '' && $indicacao !== '' ? 'IC' : 'A');

        $obs = trim(implode(' · ', array_filter([$indicacao, $funcao], static fn ($v) => $v !== '')));
        $out = [];
        foreach ($ids as $id) {
            $out[] = [
                'ecc_numero' => $ano,
                'equipe_servico_id' => $id,
                'status' => $status,
                'observacao' => $obs !== '' ? $obs : null,
            ];
        }

        return $out;
    }

    private function detectStatusInSegment(string $segment): ?string
    {
        $s = mb_strtolower($segment);
        if (preg_match('/\b(cordenou|coordenou|cordenador|coordenador)\b/u', $s) === 1) {
            return 'C';
        }
        if (preg_match('/\bcord\.?\b/u', $s) === 1) {
            return 'IC';
        }

        return null;
    }

    private function parseAceiteStatus(string $aceitou): ?string
    {
        $raw = mb_strtolower(trim($aceitou));
        if ($raw === '') {
            return null;
        }
        if (in_array($raw, ['sim', 's', 'yes', 'aceito', 'aceitou'], true)) {
            return 'A';
        }
        if (in_array($raw, ['nao', 'não', 'n', 'no'], true)) {
            return 'N';
        }

        return null;
    }

    /**
     * @param  array<string, string>  $bySlug
     * @return list<string>
     */
    private function extractEquipeIds(string $text, array $bySlug): array
    {
        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return [];
        }

        // Ordena aliases longos primeiro para "ordem e limpeza" antes de "ordem"
        $aliases = self::EQUIPE_ALIASES;
        uksort($aliases, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $found = [];
        $remaining = ' '.$normalized.' ';
        foreach ($aliases as $alias => $slug) {
            if (! isset($bySlug[$slug])) {
                continue;
            }
            $needle = ' '.$alias.' ';
            if (str_contains($remaining, $needle)) {
                $found[$bySlug[$slug]] = true;
                $remaining = str_replace($needle, ' ', $remaining);
            }
        }

        return array_keys($found);
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(['º', 'ª', '°', '/', ':', '·', '|'], ' ', $text);
        $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($trans) && $trans !== '') {
            $text = $trans;
        }
        $text = preg_replace('/[^a-z0-9\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  list<array{ecc_numero: string, equipe_servico_id: string, status: string, observacao: ?string}>  $base
     * @param  list<array{ecc_numero: string, equipe_servico_id: string, status: string, observacao: ?string}>  $extra
     * @return list<array{ecc_numero: string, equipe_servico_id: string, status: string, observacao: ?string}>
     */
    private function mergeAtividades(array $base, array $extra): array
    {
        $index = [];
        foreach ($base as $i => $row) {
            $index[$row['ecc_numero'].'|'.$row['equipe_servico_id']] = $i;
        }
        foreach ($extra as $row) {
            $key = $row['ecc_numero'].'|'.$row['equipe_servico_id'];
            if (isset($index[$key])) {
                // Status mais "forte" prevalece: C > IC > A > N
                $rank = ['NN' => 0, 'NA' => 1, 'N' => 2, 'A' => 3, 'IC' => 4, 'C' => 5];
                $cur = $base[$index[$key]];
                if (($rank[$row['status']] ?? 0) >= ($rank[$cur['status']] ?? 0)) {
                    $base[$index[$key]] = $row;
                }
            } else {
                $index[$key] = count($base);
                $base[] = $row;
            }
        }

        return $base;
    }
}
