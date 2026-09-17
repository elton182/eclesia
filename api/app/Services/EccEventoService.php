<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Casal;
use App\Models\EccEquipe;
use App\Models\EccEvento;
use App\Models\EccEventoLancamento;
use App\Models\EccItemCompra;
use App\Models\EventoAgenda;
use App\Models\EventoTipo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EccEventoService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly EventoTipoService $tipos,
    ) {}

    /**
     * @param  array{from?: string|null, to?: string|null, tipo?: string|null, tipo_id?: string|null, origem?: string|null}  $filters
     * @return Collection<int, EccEvento>
     */
    public function list(array $filters = []): Collection
    {
        $this->tipos->ensureDefaults();

        $query = EccEvento::query()
            ->where('igreja_id', $this->igrejaId())
            ->with(['eventoTipo'])
            ->withCount('casais')
            ->orderBy('inicia_em');

        if (! empty($filters['origem'])) {
            $query->where('origem', $filters['origem']);
        }
        if (! empty($filters['from'])) {
            $query->where('inicia_em', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('inicia_em', '<=', $filters['to']);
        }
        if (! empty($filters['tipo_id'])) {
            $query->where('evento_tipo_id', $filters['tipo_id']);
        } elseif (! empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        return $query->get();
    }

    public function find(string $id, ?string $origem = null): EccEvento
    {
        $query = EccEvento::query()
            ->where('igreja_id', $this->igrejaId())
            ->with([
                'eventoTipo',
                'casais.pessoaA',
                'casais.pessoaB',
                'itensCompra.doador.pessoaA',
                'itensCompra.doador.pessoaB',
                'casalCompras.pessoaA',
                'casalCompras.pessoaB',
            ])
            ->withCount('casais')
            ->whereKey($id);

        if ($origem !== null) {
            $query->where('origem', $origem);
        }

        return $query->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EccEvento
    {
        return DB::transaction(function () use ($data): EccEvento {
            $tipo = $this->resolveTipo($data);
            $origem = $data['origem'] ?? EccEvento::ORIGEM_ECC;

            $evento = EccEvento::query()->create([
                'igreja_id' => $this->igrejaId(),
                'titulo' => $data['titulo'],
                'tipo' => $tipo->codigo,
                'evento_tipo_id' => $tipo->id,
                'origem' => $origem,
                'inicia_em' => $data['inicia_em'],
                'termina_em' => $data['termina_em'] ?? null,
                'local' => $data['local'] ?? null,
                'casal_compras_id' => $data['casal_compras_id'] ?? null,
            ]);

            $this->syncEventoAgenda($evento);

            return $this->find($evento->id);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EccEvento $evento, array $data): EccEvento
    {
        return DB::transaction(function () use ($evento, $data): EccEvento {
            if (isset($data['evento_tipo_id']) || isset($data['tipo'])) {
                $tipo = $this->resolveTipo($data);
                $evento->evento_tipo_id = $tipo->id;
                $evento->tipo = $tipo->codigo;
            }

            $evento->fill([
                'titulo' => $data['titulo'] ?? $evento->titulo,
                'inicia_em' => $data['inicia_em'] ?? $evento->inicia_em,
                'termina_em' => array_key_exists('termina_em', $data) ? $data['termina_em'] : $evento->termina_em,
                'local' => array_key_exists('local', $data) ? $data['local'] : $evento->local,
                'casal_compras_id' => array_key_exists('casal_compras_id', $data) ? $data['casal_compras_id'] : $evento->casal_compras_id,
            ]);
            $evento->save();

            $this->syncEventoAgenda($evento->fresh(['eventoTipo']));

            return $this->find($evento->id);
        });
    }

    public function delete(EccEvento $evento): void
    {
        DB::transaction(function () use ($evento): void {
            $agendaId = $evento->evento_agenda_id;
            $evento->delete();

            if ($agendaId) {
                EventoAgenda::query()
                    ->where('igreja_id', $this->igrejaId())
                    ->whereKey($agendaId)
                    ->delete();
            }
        });
    }

    public function addParticipante(EccEvento $evento, string $casalId, int $convidados = 0): EccEvento
    {
        $this->assertCasalNaIgreja($casalId);

        if ($evento->casais()->where('casais.id', $casalId)->exists()) {
            throw ValidationException::withMessages([
                'casal_id' => ['Este casal já participa do evento.'],
            ]);
        }

        $evento->casais()->attach($casalId, [
            'convidados' => max(0, $convidados),
        ]);

        return $this->find($evento->id);
    }

    public function updateParticipante(EccEvento $evento, string $casalId, int $convidados): EccEvento
    {
        $this->assertCasalNaIgreja($casalId);

        if (! $evento->casais()->where('casais.id', $casalId)->exists()) {
            throw ValidationException::withMessages([
                'casal_id' => ['Casal não participa deste evento.'],
            ]);
        }

        $evento->casais()->updateExistingPivot($casalId, [
            'convidados' => max(0, $convidados),
        ]);

        return $this->find($evento->id);
    }

    public function removeParticipante(EccEvento $evento, string $casalId): void
    {
        $evento->casais()->detach($casalId);
    }

    /**
     * @return Collection<int, EccItemCompra>
     */
    public function listItens(EccEvento $evento): Collection
    {
        $this->assertPermiteCompras($evento);

        return $evento->itensCompra()
            ->with(['doador.pessoaA', 'doador.pessoaB'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addItem(EccEvento $evento, array $data): EccItemCompra
    {
        $this->assertPermiteCompras($evento);

        return $evento->itensCompra()->create([
            'nome' => $data['nome'],
            'qtd' => $data['qtd'] ?? 1,
            'unidade' => $data['unidade'] ?? 'un',
            'status' => EccItemCompra::STATUS_PENDENTE,
        ])->load(['doador.pessoaA', 'doador.pessoaB']);
    }

    public function doarItem(EccEvento $evento, EccItemCompra $item, string $casalId): EccItemCompra
    {
        $this->assertPermiteCompras($evento);
        $this->assertItemDoEvento($evento, $item);
        $this->assertCasalNaIgreja($casalId);

        if ($item->status !== EccItemCompra::STATUS_PENDENTE) {
            throw ValidationException::withMessages([
                'status' => ['Só é possível doar item pendente.'],
            ]);
        }

        $item->update([
            'status' => EccItemCompra::STATUS_DOADO,
            'doador_casal_id' => $casalId,
            'valor_gasto' => null,
        ]);

        return $item->fresh(['doador.pessoaA', 'doador.pessoaB']);
    }

    public function comprarItem(EccEvento $evento, EccItemCompra $item, float|string $valorGasto): EccItemCompra
    {
        $this->assertPermiteCompras($evento);
        $this->assertItemDoEvento($evento, $item);

        if ($item->status !== EccItemCompra::STATUS_PENDENTE) {
            throw ValidationException::withMessages([
                'status' => ['Só é possível comprar item pendente.'],
            ]);
        }

        $valor = round((float) $valorGasto, 2);
        if ($valor < 0) {
            throw ValidationException::withMessages([
                'valor_gasto' => ['Valor inválido.'],
            ]);
        }

        return DB::transaction(function () use ($evento, $item, $valor): EccItemCompra {
            $saldo = $this->saldoCaixa($evento);
            if ($valor > $saldo + 0.00001) {
                throw ValidationException::withMessages([
                    'valor_gasto' => ['Saldo em caixa insuficiente (R$ '.number_format($saldo, 2, ',', '.').').'],
                ]);
            }

            $item->update([
                'status' => EccItemCompra::STATUS_COMPRADO,
                'doador_casal_id' => null,
                'valor_gasto' => $valor,
            ]);

            if ($valor > 0) {
                EccEventoLancamento::query()->create([
                    'ecc_evento_id' => $evento->id,
                    'tipo' => EccEventoLancamento::TIPO_SAIDA,
                    'valor' => $valor,
                    'descricao' => 'Compra: '.$item->nome,
                    'ecc_item_compra_id' => $item->id,
                ]);
            }

            return $item->fresh(['doador.pessoaA', 'doador.pessoaB']);
        });
    }

    public function desfazerItem(EccEvento $evento, EccItemCompra $item): EccItemCompra
    {
        $this->assertPermiteCompras($evento);
        $this->assertItemDoEvento($evento, $item);

        return DB::transaction(function () use ($evento, $item): EccItemCompra {
            EccEventoLancamento::query()
                ->where('ecc_evento_id', $evento->id)
                ->where('ecc_item_compra_id', $item->id)
                ->where('tipo', EccEventoLancamento::TIPO_SAIDA)
                ->delete();

            $item->update([
                'status' => EccItemCompra::STATUS_PENDENTE,
                'doador_casal_id' => null,
                'valor_gasto' => null,
            ]);

            return $item->fresh(['doador.pessoaA', 'doador.pessoaB']);
        });
    }

    public function deleteItem(EccEvento $evento, EccItemCompra $item): void
    {
        $this->assertPermiteCompras($evento);
        $this->assertItemDoEvento($evento, $item);

        DB::transaction(function () use ($evento, $item): void {
            EccEventoLancamento::query()
                ->where('ecc_evento_id', $evento->id)
                ->where('ecc_item_compra_id', $item->id)
                ->delete();
            $item->delete();
        });
    }

    public function findItem(EccEvento $evento, string $itemId): EccItemCompra
    {
        $this->assertPermiteCompras($evento);

        return EccItemCompra::query()
            ->where('ecc_evento_id', $evento->id)
            ->whereKey($itemId)
            ->firstOrFail();
    }

    /**
     * Doação em dinheiro (entrada no caixa).
     * Doador: casal, equipe OU nome livre (padre, paróquia, voluntário…).
     *
     * @param  array{valor: float|string, descricao?: string|null, casal_id?: string|null, ecc_equipe_id?: string|null, doador_nome?: string|null}  $data
     */
    public function doarDinheiro(EccEvento $evento, array $data): EccEventoLancamento
    {
        $this->assertPermiteCompras($evento);

        $casalId = $data['casal_id'] ?? null;
        $equipeId = $data['ecc_equipe_id'] ?? null;
        $doadorNome = isset($data['doador_nome']) ? trim((string) $data['doador_nome']) : '';
        $hasCasal = filled($casalId);
        $hasEquipe = filled($equipeId);
        $hasNome = $doadorNome !== '';

        $modos = (int) $hasCasal + (int) $hasEquipe + (int) $hasNome;
        if ($modos !== 1) {
            throw ValidationException::withMessages([
                'doador_nome' => ['Informe exatamente um doador: casal, equipe ou nome (padre/paróquia/voluntário).'],
            ]);
        }

        if ($hasCasal) {
            $this->assertCasalNaIgreja((string) $casalId);
        }
        if ($hasEquipe) {
            $this->assertEquipeNaIgreja((string) $equipeId);
        }

        $valor = round((float) $data['valor'], 2);
        if ($valor <= 0) {
            throw ValidationException::withMessages([
                'valor' => ['Valor da doação deve ser maior que zero.'],
            ]);
        }

        return EccEventoLancamento::query()->create([
            'ecc_evento_id' => $evento->id,
            'tipo' => EccEventoLancamento::TIPO_ENTRADA,
            'valor' => $valor,
            'descricao' => $data['descricao'] ?? 'Doação em dinheiro',
            'casal_id' => $hasCasal ? $casalId : null,
            'ecc_equipe_id' => $hasEquipe ? $equipeId : null,
            'doador_nome' => $hasNome ? $doadorNome : null,
        ])->load(['casal.pessoaA', 'casal.pessoaB', 'equipe']);
    }

    /**
     * Relatório da conta corrente do evento.
     *
     * @return array<string, mixed>
     */
    public function relatorioCaixa(EccEvento $evento): array
    {
        $this->assertPermiteCompras($evento);

        $lancamentos = EccEventoLancamento::query()
            ->where('ecc_evento_id', $evento->id)
            ->with(['casal.pessoaA', 'casal.pessoaB', 'equipe', 'itemCompra'])
            ->orderBy('created_at')
            ->get();

        $entradas = $lancamentos->where('tipo', EccEventoLancamento::TIPO_ENTRADA);
        $saidas = $lancamentos->where('tipo', EccEventoLancamento::TIPO_SAIDA);
        $totalEntradas = round((float) $entradas->sum('valor'), 2);
        $totalSaidas = round((float) $saidas->sum('valor'), 2);

        $itens = $evento->itensCompra()->with(['doador.pessoaA', 'doador.pessoaB'])->orderBy('created_at')->get();

        return [
            'evento_id' => $evento->id,
            'titulo' => $evento->titulo,
            'saldo' => round($totalEntradas - $totalSaidas, 2),
            'total_entradas' => $totalEntradas,
            'total_saidas' => $totalSaidas,
            'qtd_doacoes' => $entradas->count(),
            'qtd_compras_caixa' => $saidas->whereNotNull('ecc_item_compra_id')->count(),
            'itens' => [
                'total' => $itens->count(),
                'pendentes' => $itens->where('status', EccItemCompra::STATUS_PENDENTE)->count(),
                'doados' => $itens->where('status', EccItemCompra::STATUS_DOADO)->count(),
                'comprados' => $itens->where('status', EccItemCompra::STATUS_COMPRADO)->count(),
            ],
            'extrato' => $lancamentos->map(fn (EccEventoLancamento $l) => $this->lancamentoPayload($l))->values()->all(),
        ];
    }

    public function saldoCaixa(EccEvento $evento): float
    {
        $entradas = (float) EccEventoLancamento::query()
            ->where('ecc_evento_id', $evento->id)
            ->where('tipo', EccEventoLancamento::TIPO_ENTRADA)
            ->sum('valor');
        $saidas = (float) EccEventoLancamento::query()
            ->where('ecc_evento_id', $evento->id)
            ->where('tipo', EccEventoLancamento::TIPO_SAIDA)
            ->sum('valor');

        return round($entradas - $saidas, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function lancamentoPayload(EccEventoLancamento $l): array
    {
        $doador = null;
        if ($l->casal) {
            $a = $l->casal->pessoaA?->nome ?? '';
            $b = $l->casal->pessoaB?->nome ?? '';
            $doador = ['tipo' => 'casal', 'id' => $l->casal_id, 'rotulo' => trim($a.' e '.$b, ' e')];
        } elseif ($l->equipe) {
            $doador = ['tipo' => 'equipe', 'id' => $l->ecc_equipe_id, 'rotulo' => $l->equipe->nome];
        } elseif (filled($l->doador_nome)) {
            $doador = ['tipo' => 'outro', 'id' => null, 'rotulo' => $l->doador_nome];
        }

        return [
            'id' => $l->id,
            'tipo' => $l->tipo,
            'valor' => (float) $l->valor,
            'descricao' => $l->descricao,
            'doador' => $doador,
            'item_compra_id' => $l->ecc_item_compra_id,
            'item_nome' => $l->itemCompra?->nome,
            'created_at' => $l->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveTipo(array $data): EventoTipo
    {
        $this->tipos->ensureDefaults();

        if (! empty($data['evento_tipo_id'])) {
            return $this->tipos->find((string) $data['evento_tipo_id']);
        }

        if (! empty($data['tipo'])) {
            $tipo = EventoTipo::query()
                ->where('igreja_id', $this->igrejaId())
                ->where('codigo', $data['tipo'])
                ->first();

            if ($tipo) {
                return $tipo;
            }
        }

        throw ValidationException::withMessages([
            'evento_tipo_id' => ['Tipo de evento obrigatório.'],
        ]);
    }

    private function syncEventoAgenda(EccEvento $evento): void
    {
        $dono = $evento->origem === EccEvento::ORIGEM_GERAL ? 'eventos' : 'ecc';

        $payload = [
            'igreja_id' => $this->igrejaId(),
            'titulo' => $evento->titulo,
            'inicia_em' => $evento->inicia_em,
            'termina_em' => $evento->termina_em,
            'local' => $evento->local,
            'dono_modulo' => $dono,
            'tipo' => $evento->tipo,
            'referencia_tipo' => 'ecc_evento',
            'referencia_id' => $evento->id,
        ];

        if ($evento->evento_agenda_id) {
            EventoAgenda::query()
                ->where('igreja_id', $this->igrejaId())
                ->whereKey($evento->evento_agenda_id)
                ->update($payload);

            return;
        }

        $agenda = EventoAgenda::query()->create($payload);
        $evento->update(['evento_agenda_id' => $agenda->id]);
    }

    private function assertPermiteCompras(EccEvento $evento): void
    {
        if (! $evento->relationLoaded('eventoTipo')) {
            $evento->load('eventoTipo');
        }

        if (! $evento->permiteCompras()) {
            throw ValidationException::withMessages([
                'tipo' => ['Lista de compras / caixa não disponível para este evento.'],
            ]);
        }
    }

    private function assertItemDoEvento(EccEvento $evento, EccItemCompra $item): void
    {
        if ($item->ecc_evento_id !== $evento->id) {
            abort(404);
        }
    }

    private function assertCasalNaIgreja(string $casalId): void
    {
        $exists = Casal::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($casalId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'casal_id' => ['Casal inválido para a igreja atual.'],
            ]);
        }
    }

    private function assertEquipeNaIgreja(string $equipeId): void
    {
        $exists = EccEquipe::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($equipeId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'ecc_equipe_id' => ['Equipe inválida para a igreja atual.'],
            ]);
        }
    }

    private function igrejaId(): string
    {
        return $this->igrejaContext->current()->id;
    }
}
