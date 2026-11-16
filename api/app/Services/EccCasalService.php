<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Casal;
use App\Models\EccEquipeServico;
use App\Models\Pessoa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EccCasalService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly EccEquipeService $equipes,
        private readonly EccVisibilityScope $visibility,
        private readonly PessoaFotoService $fotos,
        private readonly EccCasalImportParser $importParser,
    ) {}

    /**
     * @return Collection<int, Casal>
     */
    public function list(): Collection
    {
        $query = Casal::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->with([
                'pessoaA',
                'pessoaB',
                'equipe',
                'equipes',
                'etapas',
                'atividades.equipeServico',
                'preferencias.equipeServico',
            ]);

        $this->applyEquipeScope($query);
        $this->applyAtivoFilter($query, request()->query('ativo'));

        return $query->orderByDesc('created_at')->get();
    }

    public function find(string $id): Casal
    {
        $query = Casal::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->with([
                'pessoaA',
                'pessoaB',
                'equipe',
                'equipes',
                'etapas',
                'atividades.equipeServico',
                'preferencias.equipeServico',
            ]);

        $this->applyEquipeScope($query);

        return $query->findOrFail($id);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Casal>  $query
     */
    private function applyEquipeScope($query): void
    {
        $equipeIds = $this->visibility->restrictedEquipeIds();
        if ($equipeIds === null) {
            return;
        }

        $query->where(function ($q) use ($equipeIds): void {
            $q->whereIn('ecc_equipe_id', $equipeIds)
                ->orWhereHas('equipes', function ($eq) use ($equipeIds): void {
                    $eq->whereIn('ecc_equipes.id', $equipeIds);
                });
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Casal>  $query
     */
    private function applyAtivoFilter($query, mixed $ativo): void
    {
        if ($ativo === null || $ativo === '') {
            return;
        }
        if ($ativo === '1' || $ativo === 1 || $ativo === true || $ativo === 'true') {
            $query->where('ativo', true);

            return;
        }
        if ($ativo === '0' || $ativo === 0 || $ativo === false || $ativo === 'false') {
            $query->where('ativo', false);
        }
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
            if (! $this->visibility->userCan('ecc.casais.manage')) {
                unset($data['equipe'], $data['equipe_id'], $data['equipe_ids']);
            }

            $pessoaAData = [
                'nome' => $data['nome'],
                'email' => $data['email'] ?? null,
                'telefone' => $data['telefone'] ?? null,
                'data_nascimento' => $this->parseDate($data['data_nascimento'] ?? null),
                'sexo' => 'M',
            ];
            if (array_key_exists('ele', $data) && is_array($data['ele'])) {
                $pessoaAData = array_merge($pessoaAData, $this->pessoaExtras($data['ele']));
            }
            $casal->pessoaA->update($pessoaAData);

            $pessoaBData = [
                'nome' => $data['nome_conjuge'],
                'email' => $data['email_conjuge'] ?? null,
                'telefone' => $data['telefone_conjuge'] ?? null,
                'data_nascimento' => $this->parseDate($data['data_nascimento_conjuge'] ?? null),
                'sexo' => 'F',
            ];
            if (array_key_exists('ela', $data) && is_array($data['ela'])) {
                $pessoaBData = array_merge($pessoaBData, $this->pessoaExtras($data['ela']));
            }
            $casal->pessoaB->update($pessoaBData);

            $casal->update($this->casalAttributes($data));
            $this->syncFichaRelations($casal, $data);
            $this->syncEquipesFromPayload($casal, $data);

            return $casal->refresh()->load([
                'pessoaA',
                'pessoaB',
                'equipe',
                'equipes',
                'etapas',
                'atividades.equipeServico',
                'preferencias.equipeServico',
            ]);
        });
    }

    public function delete(Casal $casal): void
    {
        DB::transaction(function () use ($casal): void {
            $pessoaA = $casal->pessoaA;
            $pessoaB = $casal->pessoaB;
            $casal->delete();
            if ($pessoaA !== null) {
                $this->fotos->deleteFile($pessoaA);
                $pessoaA->delete();
            }
            if ($pessoaB !== null) {
                $this->fotos->deleteFile($pessoaB);
                $pessoaB->delete();
            }
        });
    }

    /**
     * Troca Ele/Ela (pessoa A ↔ B) e normaliza sexo.
     */
    public function swapPessoas(Casal $casal): Casal
    {
        return DB::transaction(function () use ($casal) {
            $aId = $casal->pessoa_a_id;
            $bId = $casal->pessoa_b_id;

            $casal->update([
                'pessoa_a_id' => $bId,
                'pessoa_b_id' => $aId,
            ]);

            $casal->refresh()->load(['pessoaA', 'pessoaB']);
            $casal->pessoaA?->update(['sexo' => 'M']);
            $casal->pessoaB?->update(['sexo' => 'F']);

            return $casal->refresh()->load(['pessoaA', 'pessoaB', 'equipe']);
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
        $catalog = $this->equipeServicoCatalog();

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            try {
                if ($this->isEmptyImportRow($row)) {
                    $skipped++;
                    continue;
                }

                $mapped = $this->mapImportRow($row, $catalog);
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
     * Pré-visualização da importação com matching e diffs (SPEC-034).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{items: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function previewImport(array $rows): array
    {
        $catalog = $this->equipeServicoCatalog();
        $index = $this->buildCoupleMatchIndex();
        $items = [];
        $summary = [
            'new' => 0,
            'match' => 0,
            'identical' => 0,
            'ambiguous' => 0,
            'invalid' => 0,
            'skipped_empty' => 0,
        ];

        foreach ($rows as $rowIndex => $row) {
            if ($this->isEmptyImportRow($row)) {
                $summary['skipped_empty']++;
                continue;
            }

            $item = $this->buildPreviewItem((int) $rowIndex, $row, $catalog, $index);
            $status = (string) $item['status'];
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            $items[] = $item;
        }

        return compact('items', 'summary');
    }

    /**
     * Aplica decisões da revisão seletiva (SPEC-034).
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $decisions
     * @return array{created: int, updated: int, skipped: int, errors: list<array{row_index: int, message: string}>}
     */
    public function commitImport(array $rows, array $decisions): array
    {
        $catalog = $this->equipeServicoCatalog();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $byIndex = [];
        foreach ($decisions as $decision) {
            if (! is_array($decision) || ! array_key_exists('row_index', $decision)) {
                continue;
            }
            $byIndex[(int) $decision['row_index']] = $decision;
        }

        foreach ($byIndex as $rowIndex => $decision) {
            $action = (string) ($decision['action'] ?? 'skip');
            if ($action === 'skip') {
                $skipped++;
                continue;
            }

            if (! array_key_exists($rowIndex, $rows) || ! is_array($rows[$rowIndex])) {
                $errors[] = [
                    'row_index' => $rowIndex,
                    'message' => 'Linha da planilha não encontrada para esta decisão.',
                ];
                continue;
            }

            $row = $rows[$rowIndex];
            try {
                if ($this->isEmptyImportRow($row)) {
                    $skipped++;
                    continue;
                }

                $mapped = $this->mapImportRow($row, $catalog);
                if ($mapped['nome'] === '' || $mapped['nome_conjuge'] === '') {
                    throw new \InvalidArgumentException('nome e nome do cônjuge são obrigatórios.');
                }

                if ($action === 'create') {
                    $this->create($mapped);
                    $created++;
                    continue;
                }

                if ($action === 'update') {
                    $casalId = (string) ($decision['casal_id'] ?? '');
                    if ($casalId === '') {
                        throw new \InvalidArgumentException('casal_id é obrigatório para update.');
                    }
                    $casal = $this->find($casalId);
                    $applyFields = $decision['apply_fields'] ?? [];
                    if (! is_array($applyFields)) {
                        $applyFields = [];
                    }
                    /** @var list<string> $applyFields */
                    $applyFields = array_values(array_filter(array_map(
                        static fn ($f) => is_string($f) ? $f : '',
                        $applyFields,
                    )));
                    if ($applyFields === []) {
                        $skipped++;
                        continue;
                    }
                    $this->applyImportFields($casal, $mapped, $applyFields);
                    $updated++;
                    continue;
                }

                throw new \InvalidArgumentException("Ação inválida: {$action}");
            } catch (Throwable $e) {
                $errors[] = [
                    'row_index' => $rowIndex,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * @return list<array{id: string, nome: string, slug: string}>
     */
    private function equipeServicoCatalog(): array
    {
        $igrejaId = $this->igrejaContext->current()->id;
        EccEquipeServico::seedDefaultsForIgreja($igrejaId);

        return EccEquipeServico::query()
            ->where('igreja_id', $igrejaId)
            ->where('ativo', true)
            ->get(['id', 'nome', 'slug'])
            ->map(static fn (EccEquipeServico $e): array => [
                'id' => (string) $e->id,
                'nome' => (string) $e->nome,
                'slug' => (string) $e->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistNew(array $data): Casal
    {
        $igrejaId = $this->igrejaContext->current()->id;

        $pessoaA = Pessoa::query()->create(array_merge([
            'igreja_id' => $igrejaId,
            'nome' => $data['nome'],
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'data_nascimento' => $this->parseDate($data['data_nascimento'] ?? null),
            'sexo' => 'M',
        ], array_key_exists('ele', $data) && is_array($data['ele'])
            ? $this->pessoaExtras($data['ele'])
            : []));

        $pessoaB = Pessoa::query()->create(array_merge([
            'igreja_id' => $igrejaId,
            'nome' => $data['nome_conjuge'],
            'email' => $data['email_conjuge'] ?? null,
            'telefone' => $data['telefone_conjuge'] ?? null,
            'data_nascimento' => $this->parseDate($data['data_nascimento_conjuge'] ?? null),
            'sexo' => 'F',
        ], array_key_exists('ela', $data) && is_array($data['ela'])
            ? $this->pessoaExtras($data['ela'])
            : []));

        $casal = Casal::query()->create(array_merge(
            [
                'igreja_id' => $igrejaId,
                'pessoa_a_id' => $pessoaA->id,
                'pessoa_b_id' => $pessoaB->id,
                'ecc_equipe_id' => null,
            ],
            $this->casalAttributes($data),
        ));

        $this->syncFichaRelations($casal, $data);
        $this->syncEquipesFromPayload($casal, $data, replace: true);

        return $casal->load([
            'pessoaA',
            'pessoaB',
            'equipe',
            'equipes',
            'etapas',
            'atividades.equipeServico',
            'preferencias.equipeServico',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function casalAttributes(array $data): array
    {
        $attrs = [
            'endereco' => $data['endereco'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'uf' => isset($data['uf']) ? strtoupper((string) $data['uf']) : null,
            'cep' => $data['cep'] ?? null,
            'data_casamento' => $this->parseDate($data['data_casamento'] ?? null),
            'filhos' => $data['filhos'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'engajamento_paroquial' => $this->stringifyCell($data['engajamento_paroquial'] ?? null),
            'habilidades' => $this->stringifyCell($data['habilidades'] ?? null),
            'piloto' => (bool) ($data['piloto'] ?? false),
            'anos_casados' => $this->parseAnosCasados($data['anos_casados'] ?? null),
            'ecc_origem' => $this->stringifyCell($data['ecc_origem'] ?? null),
            'funcao_dirigente' => $this->stringifyCell($data['funcao_dirigente'] ?? null),
            'foi_coordenador_geral' => (bool) ($data['foi_coordenador_geral'] ?? false),
        ];

        if (array_key_exists('ativo', $data)) {
            $attrs['ativo'] = (bool) $data['ativo'];
        }

        return $attrs;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncEquipesFromPayload(Casal $casal, array $data, bool $replace = false): void
    {
        $hasIds = array_key_exists('equipe_ids', $data);
        $hasSingle = ! empty($data['equipe']) || ! empty($data['equipe_id']);

        if (! $hasIds && ! $hasSingle) {
            return;
        }

        $ids = $this->resolveEquipeIdsFromData($data);

        if ($replace || $hasIds) {
            $this->syncCasalEquipes($casal, $ids);

            return;
        }

        foreach ($ids as $id) {
            $this->attachEquipeToCasal($casal, $id);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function resolveEquipeIdsFromData(array $data): array
    {
        $ids = [];

        if (isset($data['equipe_ids']) && is_array($data['equipe_ids'])) {
            foreach ($data['equipe_ids'] as $id) {
                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }
        }

        if (! empty($data['equipe_id'])) {
            $ids[] = (string) $data['equipe_id'];
        }

        if (! empty($data['equipe'])) {
            $ids[] = (string) $this->equipes->findOrCreateByNome((string) $data['equipe'])->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<string>  $equipeIds
     */
    private function syncCasalEquipes(Casal $casal, array $equipeIds): void
    {
        $casal->equipes()->sync($equipeIds);
        $this->refreshPrimaryEquipe($casal);
    }

    private function attachEquipeToCasal(Casal $casal, string $equipeId): void
    {
        $casal->equipes()->syncWithoutDetaching([$equipeId]);
        $this->refreshPrimaryEquipe($casal);
    }

    private function refreshPrimaryEquipe(Casal $casal): void
    {
        $primary = $casal->equipes()->orderBy('ecc_equipes.nome')->value('ecc_equipes.id');
        $casal->forceFill(['ecc_equipe_id' => $primary])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    private function pessoaExtras(array $extras): array
    {
        return [
            'nome_usual' => $this->stringifyCell($extras['nome_usual'] ?? null),
            'profissao' => $this->stringifyCell($extras['profissao'] ?? null),
            'religiao' => $this->stringifyCell($extras['religiao'] ?? null),
            'endereco_profissional' => $this->stringifyCell($extras['endereco_profissional'] ?? null),
            'telefone_profissional' => $this->stringifyCell($extras['telefone_profissional'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncFichaRelations(Casal $casal, array $data): void
    {
        if (array_key_exists('etapas', $data)) {
            $casal->etapas()->delete();
            foreach ($data['etapas'] ?? [] as $row) {
                if (! is_array($row) || ! isset($row['etapa'])) {
                    continue;
                }
                $casal->etapas()->create([
                    'etapa' => (int) $row['etapa'],
                    'ecc_numero' => $this->stringifyCell($row['ecc_numero'] ?? null),
                    'data' => $this->parseDate($row['data'] ?? null),
                    'local' => $this->stringifyCell($row['local'] ?? null),
                ]);
            }
        }

        if (array_key_exists('atividades', $data)) {
            $casal->atividades()->delete();
            foreach ($data['atividades'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $casal->atividades()->create([
                    'ecc_numero' => (string) ($row['ecc_numero'] ?? ''),
                    'ecc_equipe_servico_id' => $row['equipe_servico_id'],
                    'status' => (string) ($row['status'] ?? ''),
                    'observacao' => $this->stringifyCell($row['observacao'] ?? null),
                ]);
            }
        }

        if (array_key_exists('preferencias', $data)) {
            $casal->preferencias()->delete();
            $seenEquipeIds = [];
            foreach ($data['preferencias'] ?? [] as $row) {
                if (! is_array($row) || ! isset($row['equipe_servico_id']) || $row['equipe_servico_id'] === '') {
                    continue;
                }
                $equipeId = (string) $row['equipe_servico_id'];
                if (isset($seenEquipeIds[$equipeId])) {
                    continue;
                }
                $seenEquipeIds[$equipeId] = true;
                $casal->preferencias()->create([
                    'ecc_equipe_servico_id' => $equipeId,
                    'ordem' => isset($row['ordem']) ? (int) $row['ordem'] : null,
                ]);
            }
        }
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
     * Enriquece com etapas/atividades/preferências quando o catálogo é informado.
     *
     * @param  array<string, mixed>  $row
     * @param  list<array{id: string, nome: string, slug: string}>  $catalog
     * @return array<string, mixed>
     */
    private function mapImportRow(array $row, array $catalog = []): array
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

        $mapped = [
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
        ];

        if ($catalog !== []) {
            $enriched = $this->importParser->enrichFromSpreadsheetRow($row, $catalog, $get);
            if ($enriched['etapas'] !== []) {
                $mapped['etapas'] = $enriched['etapas'];
            }
            if ($enriched['atividades'] !== []) {
                $mapped['atividades'] = $enriched['atividades'];
            }
            if ($enriched['preferencias'] !== []) {
                $mapped['preferencias'] = $enriched['preferencias'];
            }
        }

        return $mapped;
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
            'ja foi circulo',
            'já foi circulo',
            'opcao para equipe dirigente',
            'opção para equipe dirigente',
        ];

        // Colunas de indicação/convite passam a alimentar atividades estruturadas;
        // mantemos só metadados livres que não viraram campo próprio.
        $extraExact = [];

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

    /**
     * @param  list<array{id: string, nome: string, slug: string}>  $catalog
     * @param  array<string, list<array<string, mixed>>>  $index
     * @return array<string, mixed>
     */
    private function buildPreviewItem(int $rowIndex, array $row, array $catalog, array $index): array
    {
        try {
            $mapped = $this->mapImportRow($row, $catalog);
        } catch (Throwable $e) {
            return [
                'row_index' => $rowIndex,
                'status' => 'invalid',
                'label' => null,
                'incoming' => null,
                'current' => null,
                'candidates' => [],
                'field_diffs' => [],
                'suggested_action' => 'skip',
                'suggested_apply_fields' => [],
                'suggested_casal_id' => null,
                'message' => $e->getMessage(),
            ];
        }

        $nome = (string) ($mapped['nome'] ?? '');
        $nomeConjuge = (string) ($mapped['nome_conjuge'] ?? '');
        $label = trim($nome.' & '.$nomeConjuge);

        if ($nome === '' || $nomeConjuge === '') {
            return [
                'row_index' => $rowIndex,
                'status' => 'invalid',
                'label' => $label !== '&' ? $label : null,
                'incoming' => $this->importComparableFromMapped($mapped),
                'current' => null,
                'candidates' => [],
                'field_diffs' => [],
                'suggested_action' => 'skip',
                'suggested_apply_fields' => [],
                'suggested_casal_id' => null,
                'message' => 'nome e nome do cônjuge são obrigatórios (ou use Nome no formato "Fulano e Ciclana").',
            ];
        }

        $key = $this->coupleMatchKey($nome, $nomeConjuge);
        $candidates = $index[$key] ?? [];
        $incoming = $this->importComparableFromMapped($mapped);

        if (count($candidates) === 0) {
            return [
                'row_index' => $rowIndex,
                'status' => 'new',
                'label' => $label,
                'incoming' => $incoming,
                'current' => null,
                'candidates' => [],
                'field_diffs' => [],
                'suggested_action' => 'create',
                'suggested_apply_fields' => [],
                'suggested_casal_id' => null,
                'message' => null,
            ];
        }

        if (count($candidates) > 1) {
            return [
                'row_index' => $rowIndex,
                'status' => 'ambiguous',
                'label' => $label,
                'incoming' => $incoming,
                'current' => null,
                'candidates' => $candidates,
                'field_diffs' => [],
                'suggested_action' => 'skip',
                'suggested_apply_fields' => [],
                'suggested_casal_id' => null,
                'message' => 'Mais de um casal com os mesmos nomes — escolha qual atualizar ou pule.',
            ];
        }

        $candidate = $candidates[0];
        $casalId = (string) $candidate['id'];
        $casal = $this->find($casalId);
        $current = $this->importComparableFromCasal($casal);
        $diffs = $this->buildFieldDiffs($current, $incoming);

        if ($diffs === []) {
            return [
                'row_index' => $rowIndex,
                'status' => 'identical',
                'label' => $label,
                'incoming' => $incoming,
                'current' => $current,
                'candidates' => $candidates,
                'field_diffs' => [],
                'suggested_action' => 'skip',
                'suggested_apply_fields' => [],
                'suggested_casal_id' => $casalId,
                'message' => null,
            ];
        }

        $suggestedApply = [];
        foreach ($diffs as $diff) {
            if (($diff['suggest_apply'] ?? false) === true) {
                $suggestedApply[] = (string) $diff['field'];
            }
        }

        return [
            'row_index' => $rowIndex,
            'status' => 'match',
            'label' => $label,
            'incoming' => $incoming,
            'current' => $current,
            'candidates' => $candidates,
            'field_diffs' => $diffs,
            'suggested_action' => 'update',
            'suggested_apply_fields' => $suggestedApply,
            'suggested_casal_id' => $casalId,
            'message' => null,
        ];
    }

    /**
     * @return array<string, list<array{id: string, nome: string, nome_conjuge: string, equipe_nome: ?string}>>
     */
    private function buildCoupleMatchIndex(): array
    {
        $index = [];
        foreach ($this->list() as $casal) {
            $nomeA = (string) ($casal->pessoaA?->nome ?? '');
            $nomeB = (string) ($casal->pessoaB?->nome ?? '');
            if ($nomeA === '' || $nomeB === '') {
                continue;
            }
            $key = $this->coupleMatchKey($nomeA, $nomeB);
            $index[$key] ??= [];
            $index[$key][] = [
                'id' => (string) $casal->id,
                'nome' => $nomeA,
                'nome_conjuge' => $nomeB,
                'equipe_nome' => $casal->equipe?->nome,
            ];
        }

        return $index;
    }

    private function coupleMatchKey(string $nomeA, string $nomeB): string
    {
        $parts = [
            $this->normalizePersonName($nomeA),
            $this->normalizePersonName($nomeB),
        ];
        sort($parts, SORT_STRING);

        return implode('|', $parts);
    }

    private function normalizePersonName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if (is_string($transliterated) && $transliterated !== '') {
            $name = $transliterated;
        }
        $name = preg_replace('/[^a-z0-9]+/u', '', $name) ?? $name;

        return $name;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function importComparableFromMapped(array $mapped): array
    {
        $out = [];
        foreach ($this->importComparableFields() as $field) {
            if (! array_key_exists($field, $mapped)) {
                continue;
            }
            $out[$field] = $this->normalizeComparableValue($field, $mapped[$field]);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function importComparableFromCasal(Casal $casal): array
    {
        $etapas = $casal->relationLoaded('etapas') ? $casal->etapas : $casal->etapas()->get();
        $atividades = $casal->relationLoaded('atividades') ? $casal->atividades : $casal->atividades()->with('equipeServico')->get();
        $preferencias = $casal->relationLoaded('preferencias') ? $casal->preferencias : $casal->preferencias()->get();

        $payload = [
            'equipe' => $casal->equipe?->nome,
            'nome' => $casal->pessoaA?->nome,
            'email' => $casal->pessoaA?->email,
            'telefone' => $casal->pessoaA?->telefone,
            'endereco' => $casal->endereco,
            'bairro' => $casal->bairro,
            'cidade' => $casal->cidade,
            'uf' => $casal->uf,
            'cep' => $casal->cep,
            'data_nascimento' => $casal->pessoaA?->data_nascimento?->toDateString(),
            'nome_conjuge' => $casal->pessoaB?->nome,
            'email_conjuge' => $casal->pessoaB?->email,
            'telefone_conjuge' => $casal->pessoaB?->telefone,
            'data_nascimento_conjuge' => $casal->pessoaB?->data_nascimento?->toDateString(),
            'data_casamento' => $casal->data_casamento?->toDateString(),
            'filhos' => $casal->filhos,
            'observacoes' => $casal->observacoes,
            'piloto' => (bool) $casal->piloto,
            'anos_casados' => $casal->anos_casados,
            'ecc_origem' => $casal->ecc_origem,
            'funcao_dirigente' => $casal->funcao_dirigente,
            'foi_coordenador_geral' => (bool) $casal->foi_coordenador_geral,
            'etapas' => $etapas->map(static fn ($e) => [
                'etapa' => (int) $e->etapa,
                'ecc_numero' => $e->ecc_numero,
                'data' => $e->data?->toDateString(),
                'local' => $e->local,
            ])->values()->all(),
            'atividades' => $atividades->map(static fn ($a) => [
                'ecc_numero' => (string) $a->ecc_numero,
                'equipe_servico_id' => (string) $a->ecc_equipe_servico_id,
                'status' => (string) $a->status,
                'observacao' => $a->observacao,
            ])->values()->all(),
            'preferencias' => $preferencias->map(static fn ($p) => [
                'equipe_servico_id' => (string) $p->ecc_equipe_servico_id,
                'ordem' => $p->ordem !== null ? (int) $p->ordem : null,
            ])->values()->all(),
        ];

        return $this->importComparableFromMapped($payload);
    }

    /**
     * @return list<string>
     */
    private function importComparableFields(): array
    {
        return [
            'equipe',
            'nome',
            'email',
            'telefone',
            'endereco',
            'bairro',
            'cidade',
            'uf',
            'cep',
            'data_nascimento',
            'nome_conjuge',
            'email_conjuge',
            'telefone_conjuge',
            'data_nascimento_conjuge',
            'data_casamento',
            'filhos',
            'observacoes',
            'piloto',
            'anos_casados',
            'ecc_origem',
            'funcao_dirigente',
            'foi_coordenador_geral',
            'etapas',
            'atividades',
            'preferencias',
        ];
    }

    private function normalizeComparableValue(string $field, mixed $value): mixed
    {
        if (in_array($field, ['piloto', 'foi_coordenador_geral'], true)) {
            return (bool) $value;
        }

        if ($field === 'anos_casados') {
            return $this->parseAnosCasados($value);
        }

        if (in_array($field, ['data_nascimento', 'data_nascimento_conjuge', 'data_casamento'], true)) {
            if ($value === null || $value === '') {
                return null;
            }
            try {
                return $this->parseDate($value);
            } catch (Throwable) {
                return is_string($value) ? trim($value) : $value;
            }
        }

        if (in_array($field, ['etapas', 'atividades', 'preferencias'], true)) {
            return is_array($value) ? array_values($value) : [];
        }

        if (in_array($field, ['uf', 'equipe'], true) && $value !== null && $value !== '') {
            return mb_strtoupper(trim((string) $value));
        }

        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return list<array{field: string, current: mixed, incoming: mixed, suggest_apply: bool}>
     */
    private function buildFieldDiffs(array $current, array $incoming): array
    {
        $diffs = [];
        foreach ($this->importComparableFields() as $field) {
            if (! array_key_exists($field, $incoming)) {
                continue;
            }
            $inVal = $incoming[$field];
            $curVal = $current[$field] ?? null;

            if ($this->comparableValuesEqual($field, $curVal, $inVal)) {
                continue;
            }

            // Planilha vazia não sobrescreve por padrão (nem aparece como conflito útil)
            if ($this->isComparableEmpty($inVal)) {
                continue;
            }

            $diffs[] = [
                'field' => $field,
                'current' => $curVal,
                'incoming' => $inVal,
                'suggest_apply' => $this->isComparableEmpty($curVal),
            ];
        }

        return $diffs;
    }

    private function comparableValuesEqual(string $field, mixed $a, mixed $b): bool
    {
        if (in_array($field, ['etapas', 'atividades', 'preferencias'], true)) {
            return json_encode($a ?? [], JSON_UNESCAPED_UNICODE) === json_encode($b ?? [], JSON_UNESCAPED_UNICODE);
        }

        $na = $this->normalizeComparableValue($field, $a);
        $nb = $this->normalizeComparableValue($field, $b);

        if ($this->isComparableEmpty($na) && $this->isComparableEmpty($nb)) {
            return true;
        }

        return $na === $nb;
    }

    private function isComparableEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }
        if (is_array($value) && $value === []) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  list<string>  $applyFields
     */
    private function applyImportFields(Casal $casal, array $incoming, array $applyFields): Casal
    {
        $allowed = array_flip($this->importComparableFields());
        $apply = [];
        foreach ($applyFields as $field) {
            if (! isset($allowed[$field]) || ! array_key_exists($field, $incoming)) {
                continue;
            }
            $apply[$field] = $incoming[$field];
        }

        if ($apply === []) {
            return $casal;
        }

        $base = $this->importComparableFromCasal($casal);
        // Evita re-sync de equipes no update a partir do snapshot; só aplica se veio em apply_fields.
        unset($base['equipe'], $base['equipe_id'], $base['equipe_ids']);
        $merged = array_merge($base, $apply);

        return $this->update($casal, $merged);
    }

    /**
     * Unifica dois registros de casal (SPEC-035). Mantém keep, absorve absorb.
     */
    public function merge(string $keepId, string $absorbId): Casal
    {
        if ($keepId === $absorbId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'absorb_id' => ['Selecione dois casais diferentes para unificar.'],
            ]);
        }

        return DB::transaction(function () use ($keepId, $absorbId) {
            $keep = $this->find($keepId);
            $absorb = $this->find($absorbId);

            $absorbEquipeIds = $absorb->equipes()->pluck('ecc_equipes.id')->map(fn ($id) => (string) $id)->all();
            if ($absorbEquipeIds !== []) {
                $keep->equipes()->syncWithoutDetaching($absorbEquipeIds);
            }

            $this->fillEmptyFromAbsorb($keep, $absorb);
            $this->reattachCasalForeignKeys($keep, $absorb);
            $this->refreshPrimaryEquipe($keep);

            $pessoaA = $absorb->pessoaA;
            $pessoaB = $absorb->pessoaB;
            $absorb->equipes()->detach();
            $absorb->delete();
            if ($pessoaA !== null) {
                $this->fotos->deleteFile($pessoaA);
                $pessoaA->delete();
            }
            if ($pessoaB !== null) {
                $this->fotos->deleteFile($pessoaB);
                $pessoaB->delete();
            }

            return $this->find((string) $keep->id);
        });
    }

    private function fillEmptyFromAbsorb(Casal $keep, Casal $absorb): void
    {
        $keep->loadMissing(['pessoaA', 'pessoaB', 'etapas', 'atividades', 'preferencias']);
        $absorb->loadMissing(['pessoaA', 'pessoaB', 'etapas', 'atividades', 'preferencias']);

        foreach (['endereco', 'bairro', 'cidade', 'uf', 'cep', 'filhos', 'observacoes', 'engajamento_paroquial', 'habilidades', 'ecc_origem', 'funcao_dirigente'] as $field) {
            if ($this->isComparableEmpty($keep->{$field}) && ! $this->isComparableEmpty($absorb->{$field})) {
                $keep->{$field} = $absorb->{$field};
            }
        }
        if ($keep->data_casamento === null && $absorb->data_casamento !== null) {
            $keep->data_casamento = $absorb->data_casamento;
        }
        if ($keep->anos_casados === null && $absorb->anos_casados !== null) {
            $keep->anos_casados = $absorb->anos_casados;
        }
        if (! $keep->piloto && $absorb->piloto) {
            $keep->piloto = true;
        }
        if (! $keep->foi_coordenador_geral && $absorb->foi_coordenador_geral) {
            $keep->foi_coordenador_geral = true;
        }
        $keep->save();

        foreach (['pessoaA' => $absorb->pessoaA, 'pessoaB' => $absorb->pessoaB] as $rel => $src) {
            $dst = $keep->{$rel};
            if ($dst === null || $src === null) {
                continue;
            }
            foreach (['email', 'telefone', 'nome_usual', 'profissao', 'religiao', 'endereco_profissional', 'telefone_profissional'] as $field) {
                if ($this->isComparableEmpty($dst->{$field}) && ! $this->isComparableEmpty($src->{$field})) {
                    $dst->{$field} = $src->{$field};
                }
            }
            if ($dst->data_nascimento === null && $src->data_nascimento !== null) {
                $dst->data_nascimento = $src->data_nascimento;
            }
            $dst->save();
        }

        if ($keep->etapas->isEmpty() && $absorb->etapas->isNotEmpty()) {
            foreach ($absorb->etapas as $etapa) {
                $keep->etapas()->create([
                    'etapa' => $etapa->etapa,
                    'ecc_numero' => $etapa->ecc_numero,
                    'data' => $etapa->data,
                    'local' => $etapa->local,
                ]);
            }
        }
    }

    private function reattachCasalForeignKeys(Casal $keep, Casal $absorb): void
    {
        $keepId = (string) $keep->id;
        $absorbId = (string) $absorb->id;

        if (Schema::hasTable('ecc_eventos')) {
            DB::table('ecc_eventos')->where('casal_compras_id', $absorbId)->update(['casal_compras_id' => $keepId]);
        }

        if (Schema::hasTable('ecc_itens_compra')) {
            DB::table('ecc_itens_compra')->where('doador_casal_id', $absorbId)->update(['doador_casal_id' => $keepId]);
        }

        if (Schema::hasTable('ecc_evento_lancamentos')) {
            DB::table('ecc_evento_lancamentos')->where('casal_id', $absorbId)->update(['casal_id' => $keepId]);
        }

        if (Schema::hasTable('ecc_evento_casal')) {
            $keepPairs = DB::table('ecc_evento_casal')
                ->where('casal_id', $keepId)
                ->pluck('ecc_evento_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $absorbRows = DB::table('ecc_evento_casal')->where('casal_id', $absorbId)->get();
            foreach ($absorbRows as $row) {
                $eventoId = (string) $row->ecc_evento_id;
                if (in_array($eventoId, $keepPairs, true)) {
                    DB::table('ecc_evento_casal')
                        ->where('ecc_evento_id', $eventoId)
                        ->where('casal_id', $absorbId)
                        ->delete();
                } else {
                    DB::table('ecc_evento_casal')
                        ->where('ecc_evento_id', $eventoId)
                        ->where('casal_id', $absorbId)
                        ->update(['casal_id' => $keepId]);
                }
            }
        }
    }
}
