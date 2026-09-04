<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Casal;
use App\Models\Pessoa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class EccCasalService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly EccEquipeService $equipes,
    ) {}

    /**
     * @return Collection<int, Casal>
     */
    public function list(): Collection
    {
        return Casal::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->with(['pessoaA', 'pessoaB', 'equipe'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function find(string $id): Casal
    {
        return Casal::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->with(['pessoaA', 'pessoaB', 'equipe'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Casal
    {
        return DB::transaction(fn () => $this->persistNew($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Casal $casal, array $data): Casal
    {
        return DB::transaction(function () use ($casal, $data) {
            $casal->pessoaA->update([
                'nome' => $data['nome'],
                'email' => $data['email'] ?? null,
                'telefone' => $data['telefone'] ?? null,
                'data_nascimento' => $this->parseDate($data['data_nascimento'] ?? null),
            ]);

            $casal->pessoaB->update([
                'nome' => $data['nome_conjuge'],
                'email' => $data['email_conjuge'] ?? null,
                'telefone' => $data['telefone_conjuge'] ?? null,
                'data_nascimento' => $this->parseDate($data['data_nascimento_conjuge'] ?? null),
            ]);

            $equipeId = $data['equipe_id'] ?? null;
            if (! empty($data['equipe']) && empty($equipeId)) {
                $equipeId = $this->equipes->findOrCreateByNome((string) $data['equipe'])->id;
            }

            $casal->update($this->casalAttributes($data, $equipeId));

            return $casal->refresh()->load(['pessoaA', 'pessoaB', 'equipe']);
        });
    }

    public function delete(Casal $casal): void
    {
        DB::transaction(function () use ($casal): void {
            $pessoaA = $casal->pessoaA;
            $pessoaB = $casal->pessoaB;
            $casal->delete();
            $pessoaA->delete();
            $pessoaB->delete();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, errors: list<array{line: int, message: string}>}
     */
    public function import(array $rows): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            try {
                if ($this->isEmptyImportRow($row)) {
                    $skipped++;
                    continue;
                }

                $mapped = $this->mapImportRow($row);
                if ($mapped['nome'] === '' || $mapped['nome_conjuge'] === '') {
                    throw new \InvalidArgumentException('nome e nome do cônjuge são obrigatórios (ou use Nome no formato "Fulano e Ciclana").');
                }
                $this->create($mapped);
                $imported++;
            } catch (Throwable $e) {
                $errors[] = [
                    'line' => $line,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistNew(array $data): Casal
    {
        $igrejaId = $this->igrejaContext->current()->id;

        $pessoaA = Pessoa::query()->create([
            'igreja_id' => $igrejaId,
            'nome' => $data['nome'],
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'data_nascimento' => $this->parseDate($data['data_nascimento'] ?? null),
        ]);

        $pessoaB = Pessoa::query()->create([
            'igreja_id' => $igrejaId,
            'nome' => $data['nome_conjuge'],
            'email' => $data['email_conjuge'] ?? null,
            'telefone' => $data['telefone_conjuge'] ?? null,
            'data_nascimento' => $this->parseDate($data['data_nascimento_conjuge'] ?? null),
        ]);

        $equipeId = $data['equipe_id'] ?? null;
        if (! empty($data['equipe']) && empty($equipeId)) {
            $equipeId = $this->equipes->findOrCreateByNome((string) $data['equipe'])->id;
        }

        return Casal::query()->create(array_merge(
            [
                'igreja_id' => $igrejaId,
                'pessoa_a_id' => $pessoaA->id,
                'pessoa_b_id' => $pessoaB->id,
            ],
            $this->casalAttributes($data, $equipeId),
        ))->load(['pessoaA', 'pessoaB', 'equipe']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function casalAttributes(array $data, mixed $equipeId): array
    {
        return [
            'ecc_equipe_id' => $equipeId,
            'endereco' => $data['endereco'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'uf' => isset($data['uf']) ? strtoupper((string) $data['uf']) : null,
            'cep' => $data['cep'] ?? null,
            'data_casamento' => $this->parseDate($data['data_casamento'] ?? null),
            'filhos' => $data['filhos'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'piloto' => (bool) ($data['piloto'] ?? false),
            'anos_casados' => $this->parseAnosCasados($data['anos_casados'] ?? null),
            'ecc_origem' => $this->stringifyCell($data['ecc_origem'] ?? null),
            'experiencia_servico' => $this->stringifyCell($data['experiencia_servico'] ?? null),
            'preferencia_funcao' => $this->stringifyCell($data['preferencia_funcao'] ?? null),
            'funcao_dirigente' => $this->stringifyCell($data['funcao_dirigente'] ?? null),
            'foi_coordenador_geral' => (bool) ($data['foi_coordenador_geral'] ?? false),
            'ficha_com_foto' => (bool) ($data['ficha_com_foto'] ?? false),
            'etapa_2' => $this->stringifyCell($data['etapa_2'] ?? null),
            'etapa_3' => $this->stringifyCell($data['etapa_3'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isEmptyImportRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Aceita o modelo detalhado (nome + nome conjuge) e o modelo real do ecc.xlsx
     * (coluna Nome = "Fulano e Ciclana", Endereço, Telefone, Equipe…).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapImportRow(array $row): array
    {
        $get = function (array $keys) use ($row): mixed {
            return $this->pickColumn($row, $keys);
        };

        $nome = (string) ($get(['nome']) ?? '');
        $nomeConjuge = (string) ($get(['nome conjuge', 'nome_conjuge', 'conjuge', 'cônjuge']) ?? '');

        // Formato ecc.xlsx: um único "Nome" com o casal ("Luiz e Vera Lúcia")
        if ($nome !== '' && $nomeConjuge === '') {
            [$nome, $nomeConjuge] = $this->splitCoupleName($nome);
        }

        $telefone = $get(['telefone']);
        $telefoneConjuge = $get(['telefone conjuge', 'telefone_conjuge']);
        if (($telefoneConjuge === null || $telefoneConjuge === '') && is_string($telefone) && str_contains($telefone, '/')) {
            [$telefone, $telefoneConjuge] = array_map('trim', explode('/', $telefone, 2));
        }

        $filhos = $get([
            'filhos',
            'têm filhos? se sim, qual idade?',
            'tem filhos? se sim, qual idade?',
            'têm filhos',
        ]);

        $observacoes = $this->buildObservacoesExtras($row, $get([
            'observacoes',
            'observações',
        ]));

        return [
            'equipe' => (string) ($get(['equipe']) ?? ''),
            'nome' => $nome,
            'email' => $get(['e-mail', 'email']),
            'telefone' => $this->stringifyCell($telefone),
            'endereco' => $get(['endereco', 'endereço']),
            'bairro' => $get(['bairro']),
            'cidade' => $get(['cidade']),
            'uf' => $get(['uf']),
            'cep' => $get(['cep']),
            'data_nascimento' => $get(['data nascimento', 'data_nascimento']),
            'nome_conjuge' => $nomeConjuge,
            'email_conjuge' => $get(['e-mail conjuge', 'email conjuge', 'email_conjuge']),
            'telefone_conjuge' => $this->stringifyCell($telefoneConjuge),
            'data_nascimento_conjuge' => $get(['data nascimento conjuge', 'data_nascimento_conjuge']),
            'data_casamento' => $get(['data casamento', 'data_casamento']),
            'filhos' => $this->stringifyCell($filhos),
            'observacoes' => $observacoes !== '' ? $observacoes : null,
            'piloto' => $this->parseBool($get(['piloto'])),
            'anos_casados' => $get(['quanto tempo de casados?', 'anos de casados', 'tempo de casados', 'anos_casados']),
            'ecc_origem' => $get(['qual ecc vocês fizeram?', 'qual ecc voces fizeram?', 'qual ecc vocês fizeram', 'ecc_origem']),
            'experiencia_servico' => $get([
                'já trabalharam no encontro do ecc?',
                'ja trabalharam no encontro do ecc?',
                'já trabalharam no encontro do ecc',
                'experiencia_servico',
            ]),
            'preferencia_funcao' => $get([
                'em qual função você gostaria de trabalhar?',
                'em qual funcao voce gostaria de trabalhar?',
                'em qual função você gostaria de trabalhar',
                'preferencia_funcao',
            ]),
            'funcao_dirigente' => $get([
                'casal dirigente? qual função',
                'casal dirigente? qual funcao',
                'funcao_dirigente',
            ]),
            'foi_coordenador_geral' => $this->parseBool($get([
                'já foi cordenador geral?',
                'ja foi cordenador geral?',
                'já foi coordenador geral?',
                'foi_coordenador_geral',
            ])),
            'ficha_com_foto' => $this->parseBool($get(['ficha com foto:', 'ficha com foto', 'ficha_com_foto'])),
            'etapa_2' => $get(['tem 2ª etapa', 'tem 2a etapa', 'etapa_2']),
            'etapa_3' => $get(['tem 3ª etapa', 'tem 3a etapa', 'etapa_3']),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function pickColumn(array $row, array $keys): mixed
    {
        $normalizedKeys = array_map(fn (string $k) => $this->normalizeHeader($k), $keys);

        foreach ($row as $header => $value) {
            $normalizedHeader = $this->normalizeHeader((string) $header);
            if (in_array($normalizedHeader, $normalizedKeys, true)) {
                if (is_string($value)) {
                    return trim($value);
                }

                return $value;
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
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

    /**
     * @return array{0: string, 1: string}
     */
    private function splitCoupleName(string $nome): array
    {
        $nome = trim(preg_replace('/\s+/u', ' ', $nome) ?? $nome);

        // "Wilson ( Nenê )e Fátima" → normaliza ")e " / ") e "
        $nome = preg_replace('/\)\s*e\s+/iu', ') e ', $nome) ?? $nome;

        foreach ([' e ', ' & ', ' E ', ' / ', ' - ', ' – ', ' — '] as $separator) {
            $parts = explode($separator, $nome, 2);
            if (count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
                return [trim($parts[0]), trim($parts[1])];
            }
        }

        if (preg_match('/^(.+?)\s+e\s+(.+)$/iu', $nome, $matches) === 1) {
            return [trim($matches[1]), trim($matches[2])];
        }

        // Pessoa sem casal (ex.: viúva) — usa marcador para satisfazer o modelo atual
        if (preg_match('/viuv/iu', $nome) === 1) {
            return [$nome, '(sem cônjuge)'];
        }

        return [$nome, ''];
    }

    /**
     * Só colunas históricas/livres que não viraram campo próprio.
     *
     * @param  array<string, mixed>  $row
     */
    private function buildObservacoesExtras(array $row, mixed $base): string
    {
        $parts = [];
        if ($base !== null && $base !== '') {
            $parts[] = (string) $base;
        }

        $extraExactOrPrefix = [
            'n casais',
            '2023 convite para',
            'aceitou',
            'ja foi circulo',
            'já foi circulo',
            'indicacao para 2024',
            'indicação para 2024',
            'indicacao para 2025',
            'indicação para 2025',
            'opcao para equipe dirigente',
            'opção para equipe dirigente',
        ];

        // "Função" / "Função_1" / "Aceitou_1" da planilha (histórico)
        $extraExact = [
            'funcao',
            'função',
            'funcao 1',
            'função 1',
            'aceitou 1',
        ];

        foreach ($row as $header => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $normalized = $this->normalizeHeader((string) $header);
            $match = in_array($normalized, $extraExact, true);
            if (! $match) {
                foreach ($extraExactOrPrefix as $wanted) {
                    $w = $this->normalizeHeader($wanted);
                    if ($normalized === $w || str_starts_with($normalized, $w)) {
                        $match = true;
                        break;
                    }
                }
            }
            if ($match) {
                $parts[] = trim((string) $header).': '.$this->stringifyCell($value);
            }
        }

        return implode("\n", $parts);
    }

    private function parseBool(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        $raw = mb_strtolower(trim((string) $value));

        return in_array($raw, ['1', 'true', 'sim', 's', 'yes', 'y'], true);
    }

    private function parseAnosCasados(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }
        if (preg_match('/(\d+)/', (string) $value, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    private function stringifyCell(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_float($value) || is_int($value)) {
            // Telefone/CEP às vezes vêm como número
            if (is_float($value) && floor($value) === $value) {
                return (string) (int) $value;
            }

            return (string) $value;
        }

        return trim((string) $value);
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        // Serial Excel (dias desde 1899-12-30)
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            try {
                return Carbon::createFromTimestampUTC(((int) $value - 25569) * 86400)->toDateString();
            } catch (Throwable) {
                // fall through
            }
        }

        $raw = trim((string) $value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $raw);

                return $dt !== false ? $dt->toDateString() : null;
            } catch (Throwable) {
                // try next
            }
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (Throwable) {
            throw new \InvalidArgumentException("Data inválida: {$raw}");
        }
    }
}
