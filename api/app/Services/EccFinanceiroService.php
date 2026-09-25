<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EccFinanceiroConta;
use App\Models\EccFinanceiroLancamento;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EccFinanceiroService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
    ) {}

    public function ensureContasPadrao(): void
    {
        $igrejaId = $this->igrejaId();

        if (EccFinanceiroConta::query()->where('igreja_id', $igrejaId)->exists()) {
            return;
        }

        EccFinanceiroConta::query()->create([
            'igreja_id' => $igrejaId,
            'nome' => 'Conta ECC (paróquia)',
            'tipo' => EccFinanceiroConta::TIPO_BANCO,
            'ordem' => 1,
            'ativa' => true,
        ]);

        EccFinanceiroConta::query()->create([
            'igreja_id' => $igrejaId,
            'nome' => 'Espécie / conta particular',
            'tipo' => EccFinanceiroConta::TIPO_ESPECIE,
            'ordem' => 2,
            'ativa' => true,
        ]);
    }

    /**
     * @return Collection<int, EccFinanceiroConta>
     */
    public function listContas(bool $apenasAtivas = false): Collection
    {
        $this->ensureContasPadrao();

        $query = EccFinanceiroConta::query()
            ->where('igreja_id', $this->igrejaId())
            ->orderBy('ordem')
            ->orderBy('nome');

        if ($apenasAtivas) {
            $query->where('ativa', true);
        }

        return $query->get();
    }

    public function findConta(string $id): EccFinanceiroConta
    {
        return EccFinanceiroConta::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @param  array{nome: string, tipo: string, ordem?: int, ativa?: bool}  $data
     */
    public function createConta(array $data): EccFinanceiroConta
    {
        $this->ensureContasPadrao();

        $ordem = $data['ordem'] ?? ((int) EccFinanceiroConta::query()
            ->where('igreja_id', $this->igrejaId())
            ->max('ordem') + 1);

        return EccFinanceiroConta::query()->create([
            'igreja_id' => $this->igrejaId(),
            'nome' => $data['nome'],
            'tipo' => $data['tipo'],
            'ordem' => $ordem,
            'ativa' => $data['ativa'] ?? true,
        ]);
    }

    /**
     * @param  array{nome?: string, tipo?: string, ordem?: int, ativa?: bool}  $data
     */
    public function updateConta(EccFinanceiroConta $conta, array $data): EccFinanceiroConta
    {
        $conta->fill(array_intersect_key($data, array_flip(['nome', 'tipo', 'ordem', 'ativa'])));
        $conta->save();

        return $conta->refresh();
    }

    public function deleteConta(EccFinanceiroConta $conta): void
    {
        if ($conta->lancamentos()->exists()) {
            throw ValidationException::withMessages([
                'conta' => ['Conta com lançamentos não pode ser excluída. Desative-a.'],
            ]);
        }

        $conta->delete();
    }

    /**
     * @return array{
     *     ano: int,
     *     contas: list<array<string, mixed>>,
     *     meses: list<array<string, mixed>>,
     *     saldo_final: float
     * }
     */
    public function livro(int $ano): array
    {
        $this->assertAno($ano);
        $contas = $this->listContas();

        $inicio = sprintf('%04d-01-01', $ano);
        $fim = sprintf('%04d-12-31', $ano);

        $lancamentos = EccFinanceiroLancamento::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereBetween('data', [$inicio, $fim])
            ->orderBy('data')
            ->orderBy('created_at')
            ->get();

        $saldosAbertura = [];
        foreach ($contas as $conta) {
            $saldosAbertura[$conta->id] = 0.0;
        }

        foreach ($lancamentos->where('abertura', true) as $abertura) {
            $contaId = $abertura->ecc_financeiro_conta_id;
            $delta = $abertura->tipo === EccFinanceiroLancamento::TIPO_ENTRADA
                ? (float) $abertura->valor
                : -1 * (float) $abertura->valor;
            $saldosAbertura[$contaId] = ($saldosAbertura[$contaId] ?? 0.0) + $delta;
        }

        $meses = [];
        $acumulado = array_sum($saldosAbertura);

        for ($mes = 1; $mes <= 12; $mes++) {
            $doMes = $lancamentos->filter(
                static fn (EccFinanceiroLancamento $l): bool => (int) $l->data->format('n') === $mes
                    && ! $l->abertura
            );

            $totaisPorConta = [];
            foreach ($contas as $conta) {
                $totaisPorConta[$conta->id] = [
                    'entradas' => 0.0,
                    'saidas' => 0.0,
                    'saldo' => 0.0,
                ];
            }

            $itens = [];
            foreach ($doMes as $lanc) {
                $contaId = $lanc->ecc_financeiro_conta_id;
                if (! isset($totaisPorConta[$contaId])) {
                    $totaisPorConta[$contaId] = [
                        'entradas' => 0.0,
                        'saidas' => 0.0,
                        'saldo' => 0.0,
                    ];
                }

                if ($lanc->tipo === EccFinanceiroLancamento::TIPO_ENTRADA) {
                    $totaisPorConta[$contaId]['entradas'] += (float) $lanc->valor;
                } else {
                    $totaisPorConta[$contaId]['saidas'] += (float) $lanc->valor;
                }

                $itens[] = $this->serializeLancamento($lanc);
            }

            $totalMensal = 0.0;
            foreach ($totaisPorConta as $contaId => $totais) {
                $saldo = $totais['entradas'] - $totais['saidas'];
                $totaisPorConta[$contaId]['saldo'] = round($saldo, 2);
                $totaisPorConta[$contaId]['entradas'] = round($totais['entradas'], 2);
                $totaisPorConta[$contaId]['saidas'] = round($totais['saidas'], 2);
                $totalMensal += $saldo;
            }

            $totalMensal = round($totalMensal, 2);
            $acumulado = round($acumulado + $totalMensal, 2);

            // Inclui aberturas no mês 1 para o extrato
            $aberturasMes = $mes === 1
                ? $lancamentos->filter(
                    static fn (EccFinanceiroLancamento $l): bool => $l->abertura
                        && (int) $l->data->format('n') === 1
                )->map(fn (EccFinanceiroLancamento $l): array => $this->serializeLancamento($l))->values()->all()
                : [];

            $meses[] = [
                'mes' => $mes,
                'lancamentos' => array_values(array_merge($aberturasMes, $itens)),
                'totais_por_conta' => $totaisPorConta,
                'total_mensal' => $totalMensal,
                'acumulado' => $acumulado,
            ];
        }

        $saldosFinais = $saldosAbertura;
        foreach ($meses as $bloco) {
            foreach ($bloco['totais_por_conta'] as $contaId => $totais) {
                $saldosFinais[$contaId] = round(($saldosFinais[$contaId] ?? 0.0) + $totais['saldo'], 2);
            }
        }

        $contasPayload = $contas->map(static function (EccFinanceiroConta $conta) use ($saldosAbertura, $saldosFinais): array {
            return [
                'id' => $conta->id,
                'nome' => $conta->nome,
                'tipo' => $conta->tipo,
                'ordem' => $conta->ordem,
                'ativa' => $conta->ativa,
                'saldo_abertura' => round($saldosAbertura[$conta->id] ?? 0.0, 2),
                'saldo_final' => round($saldosFinais[$conta->id] ?? 0.0, 2),
            ];
        })->values()->all();

        return [
            'ano' => $ano,
            'contas' => $contasPayload,
            'meses' => $meses,
            'saldo_final' => round(array_sum($saldosFinais), 2),
        ];
    }

    /**
     * @param  array{conta_id: string, data: string, historico: string, tipo: string, valor: float|int|string}  $data
     */
    public function createLancamento(array $data): EccFinanceiroLancamento
    {
        $conta = $this->findConta($data['conta_id']);
        $this->assertContaAtiva($conta);

        return EccFinanceiroLancamento::query()->create([
            'igreja_id' => $this->igrejaId(),
            'ecc_financeiro_conta_id' => $conta->id,
            'data' => $data['data'],
            'historico' => $data['historico'],
            'tipo' => $data['tipo'],
            'valor' => $data['valor'],
            'transferencia_id' => null,
            'abertura' => false,
        ]);
    }

    public function findLancamento(string $id): EccFinanceiroLancamento
    {
        return EccFinanceiroLancamento::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @param  array{data?: string, historico?: string, tipo?: string, valor?: float|int|string, conta_id?: string}  $data
     * @return list<EccFinanceiroLancamento>
     */
    public function updateLancamento(EccFinanceiroLancamento $lancamento, array $data): array
    {
        return DB::transaction(function () use ($lancamento, $data): array {
            $pares = $this->paresTransferencia($lancamento);

            if (count($pares) === 1) {
                if (isset($data['conta_id'])) {
                    $conta = $this->findConta($data['conta_id']);
                    $this->assertContaAtiva($conta);
                    $lancamento->ecc_financeiro_conta_id = $conta->id;
                }
                if (isset($data['tipo'])) {
                    $lancamento->tipo = $data['tipo'];
                }
                if (isset($data['data'])) {
                    $lancamento->data = $data['data'];
                }
                if (isset($data['historico'])) {
                    $lancamento->historico = $data['historico'];
                }
                if (isset($data['valor'])) {
                    $lancamento->valor = $data['valor'];
                }
                $lancamento->save();

                return [$lancamento->refresh()];
            }

            // Transferência: atualiza valor/data/historico nos dois; tipo e conta não mudam de lado.
            $valor = $data['valor'] ?? $lancamento->valor;
            $dataLanc = $data['data'] ?? $lancamento->data->format('Y-m-d');
            $historico = $data['historico'] ?? $lancamento->historico;

            foreach ($pares as $par) {
                $par->data = $dataLanc;
                $par->historico = $historico;
                $par->valor = $valor;
                $par->save();
            }

            return array_map(
                static fn (EccFinanceiroLancamento $l): EccFinanceiroLancamento => $l->refresh(),
                $pares
            );
        });
    }

    public function deleteLancamento(EccFinanceiroLancamento $lancamento): void
    {
        DB::transaction(function () use ($lancamento): void {
            foreach ($this->paresTransferencia($lancamento) as $par) {
                $par->delete();
            }
        });
    }

    /**
     * @param  array{conta_origem_id: string, conta_destino_id: string, data: string, historico: string, valor: float|int|string}  $data
     * @return array{saida: EccFinanceiroLancamento, entrada: EccFinanceiroLancamento}
     */
    public function transferir(array $data): array
    {
        if ($data['conta_origem_id'] === $data['conta_destino_id']) {
            throw ValidationException::withMessages([
                'conta_destino_id' => ['A conta de destino deve ser diferente da origem.'],
            ]);
        }

        $origem = $this->findConta($data['conta_origem_id']);
        $destino = $this->findConta($data['conta_destino_id']);
        $this->assertContaAtiva($origem);
        $this->assertContaAtiva($destino);

        $transferenciaId = (string) Str::ulid();

        return DB::transaction(function () use ($data, $origem, $destino, $transferenciaId): array {
            $saida = EccFinanceiroLancamento::query()->create([
                'igreja_id' => $this->igrejaId(),
                'ecc_financeiro_conta_id' => $origem->id,
                'data' => $data['data'],
                'historico' => $data['historico'],
                'tipo' => EccFinanceiroLancamento::TIPO_SAIDA,
                'valor' => $data['valor'],
                'transferencia_id' => $transferenciaId,
                'abertura' => false,
            ]);

            $entrada = EccFinanceiroLancamento::query()->create([
                'igreja_id' => $this->igrejaId(),
                'ecc_financeiro_conta_id' => $destino->id,
                'data' => $data['data'],
                'historico' => $data['historico'],
                'tipo' => EccFinanceiroLancamento::TIPO_ENTRADA,
                'valor' => $data['valor'],
                'transferencia_id' => $transferenciaId,
                'abertura' => false,
            ]);

            return ['saida' => $saida, 'entrada' => $entrada];
        });
    }

    /**
     * @return list<EccFinanceiroLancamento>
     */
    public function transportar(int $ano): array
    {
        $this->assertAno($ano);
        $this->ensureContasPadrao();

        $inicio = sprintf('%04d-01-01', $ano);
        $jaTem = EccFinanceiroLancamento::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('abertura', true)
            ->whereBetween('data', [$inicio, sprintf('%04d-12-31', $ano)])
            ->exists();

        if ($jaTem) {
            throw ValidationException::withMessages([
                'ano' => ['Este ano já possui lançamentos de abertura.'],
            ]);
        }

        $livroAnterior = $this->livro($ano - 1);
        $saldos = [];
        foreach ($livroAnterior['contas'] as $conta) {
            $saldos[$conta['id']] = (float) $conta['saldo_final'];
        }

        $contas = $this->listContas(apenasAtivas: true);
        $criados = [];

        DB::transaction(function () use ($ano, $contas, $saldos, &$criados): void {
            foreach ($contas as $conta) {
                $saldo = round($saldos[$conta->id] ?? 0.0, 2);
                if ($saldo == 0.0) {
                    continue;
                }

                $tipo = $saldo >= 0
                    ? EccFinanceiroLancamento::TIPO_ENTRADA
                    : EccFinanceiroLancamento::TIPO_SAIDA;

                $criados[] = EccFinanceiroLancamento::query()->create([
                    'igreja_id' => $this->igrejaId(),
                    'ecc_financeiro_conta_id' => $conta->id,
                    'data' => sprintf('%04d-01-01', $ano),
                    'historico' => 'Saldo transportado de '.($ano - 1),
                    'tipo' => $tipo,
                    'valor' => abs($saldo),
                    'transferencia_id' => null,
                    'abertura' => true,
                ]);
            }

            if ($criados === []) {
                $primeira = $contas->first();
                if ($primeira === null) {
                    return;
                }
                $criados[] = EccFinanceiroLancamento::query()->create([
                    'igreja_id' => $this->igrejaId(),
                    'ecc_financeiro_conta_id' => $primeira->id,
                    'data' => sprintf('%04d-01-01', $ano),
                    'historico' => 'Saldo transportado de '.($ano - 1),
                    'tipo' => EccFinanceiroLancamento::TIPO_ENTRADA,
                    'valor' => 0,
                    'transferencia_id' => null,
                    'abertura' => true,
                ]);
            }
        });

        return $criados;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeLancamento(EccFinanceiroLancamento $lancamento): array
    {
        return [
            'id' => $lancamento->id,
            'conta_id' => $lancamento->ecc_financeiro_conta_id,
            'data' => $lancamento->data->format('Y-m-d'),
            'historico' => $lancamento->historico,
            'tipo' => $lancamento->tipo,
            'valor' => round((float) $lancamento->valor, 2),
            'transferencia_id' => $lancamento->transferencia_id,
            'abertura' => (bool) $lancamento->abertura,
        ];
    }

    private function igrejaId(): string
    {
        return $this->igrejaContext->current()->id;
    }

    /**
     * Importa lançamentos parseados da planilha FLUXO CAIXA ECC.
     *
     * @param  array{
     *     modo?: string,
     *     anos: list<array{
     *         ano: int,
     *         contas?: list<array{nome?: string, tipo: string}>,
     *         lancamentos: list<array{
     *             data: string,
     *             historico: string,
     *             conta_tipo: string,
     *             tipo: string,
     *             valor: float|int|string,
     *             abertura?: bool,
     *             transferencia_key?: string|null
     *         }>
     *     }>
     * }  $payload
     * @return array{imported: int, skipped: int, anos: list<int>, errors: list<array{line: int, message: string}>}
     */
    public function importFromPlanilha(array $payload): array
    {
        $this->ensureContasPadrao();

        $modo = $payload['modo'] ?? 'replace';
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $anosProcessados = [];

        $contasPorTipo = $this->listContas()->keyBy('tipo');

        return DB::transaction(function () use (
            $payload,
            $modo,
            &$imported,
            &$skipped,
            &$errors,
            &$anosProcessados,
            $contasPorTipo
        ): array {
            foreach ($payload['anos'] as $anoIdx => $blocoAno) {
                $ano = (int) ($blocoAno['ano'] ?? 0);
                if ($ano < 2000 || $ano > 2100) {
                    $errors[] = ['line' => $anoIdx + 1, 'message' => 'Ano inválido.'];
                    continue;
                }

                $anosProcessados[] = $ano;

                if ($modo === 'replace') {
                    $this->apagarLancamentosDoAno($ano);
                }

                // Atualiza nomes das contas padrão se a planilha trouxer
                foreach ($blocoAno['contas'] ?? [] as $meta) {
                    $tipo = (string) ($meta['tipo'] ?? '');
                    $nome = trim((string) ($meta['nome'] ?? ''));
                    if ($nome !== '' && isset($contasPorTipo[$tipo])) {
                        $contasPorTipo[$tipo]->nome = $nome;
                        $contasPorTipo[$tipo]->save();
                    }
                }

                /** @var array<string, string> $transferMap key → transferencia_id */
                $transferMap = [];
                $lineBase = ($anoIdx + 1) * 1000;

                foreach ($blocoAno['lancamentos'] ?? [] as $i => $row) {
                    $line = $lineBase + $i + 1;
                    try {
                        $tipoConta = (string) ($row['conta_tipo'] ?? '');
                        $conta = $contasPorTipo[$tipoConta] ?? null;
                        if ($conta === null) {
                            throw new \InvalidArgumentException("Conta tipo '{$tipoConta}' não encontrada.");
                        }

                        $tipo = (string) ($row['tipo'] ?? '');
                        if (! in_array($tipo, [EccFinanceiroLancamento::TIPO_ENTRADA, EccFinanceiroLancamento::TIPO_SAIDA], true)) {
                            throw new \InvalidArgumentException('Tipo inválido.');
                        }

                        $valor = round((float) $row['valor'], 2);
                        if ($valor < 0) {
                            throw new \InvalidArgumentException('Valor inválido.');
                        }

                        $historico = trim((string) ($row['historico'] ?? ''));
                        if ($historico === '') {
                            throw new \InvalidArgumentException('Histórico obrigatório.');
                        }

                        $data = (string) ($row['data'] ?? '');
                        if ($data === '') {
                            throw new \InvalidArgumentException('Data obrigatória.');
                        }

                        $abertura = (bool) ($row['abertura'] ?? false);
                        $tKey = $row['transferencia_key'] ?? null;
                        $transferenciaId = null;
                        if (is_string($tKey) && $tKey !== '') {
                            if (! isset($transferMap[$tKey])) {
                                $transferMap[$tKey] = (string) Str::ulid();
                            }
                            $transferenciaId = $transferMap[$tKey];
                        }

                        if ($modo === 'merge' && $this->lancamentoDuplicado(
                            $conta->id,
                            $data,
                            $historico,
                            $tipo,
                            $valor,
                            $abertura
                        )) {
                            $skipped++;
                            continue;
                        }

                        EccFinanceiroLancamento::query()->create([
                            'igreja_id' => $this->igrejaId(),
                            'ecc_financeiro_conta_id' => $conta->id,
                            'data' => $data,
                            'historico' => $historico,
                            'tipo' => $tipo,
                            'valor' => $valor,
                            'transferencia_id' => $transferenciaId,
                            'abertura' => $abertura,
                        ]);
                        $imported++;
                    } catch (\Throwable $e) {
                        $errors[] = ['line' => $line, 'message' => $e->getMessage()];
                    }
                }
            }

            return [
                'imported' => $imported,
                'skipped' => $skipped,
                'anos' => array_values(array_unique($anosProcessados)),
                'errors' => $errors,
            ];
        });
    }

    /**
     * @return list<int>
     */
    public function anosComMovimento(): array
    {
        $this->ensureContasPadrao();

        return EccFinanceiroLancamento::query()
            ->where('igreja_id', $this->igrejaId())
            ->get(['data'])
            ->map(static fn (EccFinanceiroLancamento $l): int => (int) $l->data->format('Y'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function apagarLancamentosDoAno(int $ano): void
    {
        EccFinanceiroLancamento::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereBetween('data', [sprintf('%04d-01-01', $ano), sprintf('%04d-12-31', $ano)])
            ->delete();
    }

    private function lancamentoDuplicado(
        string $contaId,
        string $data,
        string $historico,
        string $tipo,
        float $valor,
        bool $abertura,
    ): bool {
        return EccFinanceiroLancamento::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('ecc_financeiro_conta_id', $contaId)
            ->whereDate('data', $data)
            ->where('tipo', $tipo)
            ->where('valor', $valor)
            ->where('abertura', $abertura)
            ->get()
            ->contains(static fn (EccFinanceiroLancamento $l): bool => $l->historico === $historico);
    }

    private function assertAno(int $ano): void
    {
        if ($ano < 2000 || $ano > 2100) {
            throw ValidationException::withMessages([
                'ano' => ['Ano inválido.'],
            ]);
        }
    }

    private function assertContaAtiva(EccFinanceiroConta $conta): void
    {
        if (! $conta->ativa) {
            throw ValidationException::withMessages([
                'conta_id' => ['Conta inativa.'],
            ]);
        }
    }

    /**
     * @return list<EccFinanceiroLancamento>
     */
    private function paresTransferencia(EccFinanceiroLancamento $lancamento): array
    {
        if ($lancamento->transferencia_id === null) {
            return [$lancamento];
        }

        return EccFinanceiroLancamento::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('transferencia_id', $lancamento->transferencia_id)
            ->get()
            ->all();
    }
}
