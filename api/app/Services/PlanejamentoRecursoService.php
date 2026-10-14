<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanejamentoEvento;
use App\Models\PlanejamentoRecurso;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de espaços/recursos e regra de capacidade (SPEC-019).
 */
class PlanejamentoRecursoService
{
    public function __construct(private readonly IgrejaContext $igrejaContext) {}

    /**
     * @return Collection<int, PlanejamentoRecurso>
     */
    public function list(bool $somenteAtivos, ?string $tipo = null): Collection
    {
        return PlanejamentoRecurso::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->when($somenteAtivos, fn ($q) => $q->where('ativo', true))
            ->when($tipo !== null, fn ($q) => $q->where('tipo', $tipo))
            ->orderBy('tipo')
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();
    }

    public function find(string $id): PlanejamentoRecurso
    {
        return PlanejamentoRecurso::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PlanejamentoRecurso
    {
        $igrejaId = $this->igrejaContext->current()->id;
        $this->assertNomeUnico($igrejaId, (string) $data['nome']);

        return PlanejamentoRecurso::query()->create([
            'igreja_id' => $igrejaId,
            'nome' => $data['nome'],
            'tipo' => $data['tipo'],
            'quantidade' => $this->normalizarQuantidade($data['tipo'], $data['quantidade'] ?? 1),
            'descricao' => $data['descricao'] ?? null,
            'ativo' => $data['ativo'] ?? true,
            'ordem' => $data['ordem'] ?? 0,
        ])->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PlanejamentoRecurso $recurso, array $data): PlanejamentoRecurso
    {
        if (isset($data['nome']) && $data['nome'] !== $recurso->nome) {
            $this->assertNomeUnico($recurso->igreja_id, (string) $data['nome'], $recurso->id);
        }

        $recurso->fill(array_intersect_key($data, array_flip([
            'nome', 'tipo', 'quantidade', 'descricao', 'ativo', 'ordem',
        ])));
        $recurso->quantidade = $this->normalizarQuantidade($recurso->tipo, $recurso->quantidade);
        $recurso->save();

        return $recurso->refresh();
    }

    /**
     * Exclui; se já alocado em algum evento, apenas inativa e devolve o item.
     */
    public function delete(PlanejamentoRecurso $recurso): ?PlanejamentoRecurso
    {
        if ($recurso->eventos()->exists()) {
            $recurso->ativo = false;
            $recurso->save();

            return $recurso->refresh();
        }

        $recurso->delete();

        return null;
    }

    /**
     * Valida e normaliza o pedido do evento → [recurso_id => quantidade].
     * Itens já alocados ao evento podem permanecer mesmo se inativados depois.
     *
     * @param  list<array{recurso_id: string, quantidade?: int|null}>  $itens
     * @param  list<string>  $jaAlocados
     * @return array<string, int>
     */
    public function resolverPedido(array $itens, array $jaAlocados = []): array
    {
        $pedido = [];
        foreach ($itens as $item) {
            $id = (string) $item['recurso_id'];
            $pedido[$id] = ($pedido[$id] ?? 0) + max(1, (int) ($item['quantidade'] ?? 1));
        }

        if ($pedido === []) {
            return [];
        }

        $recursos = PlanejamentoRecurso::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->whereIn('id', array_keys($pedido))
            ->get()
            ->keyBy('id');

        foreach (array_keys($pedido) as $id) {
            $recurso = $recursos->get($id);
            if ($recurso === null) {
                throw ValidationException::withMessages([
                    'recursos' => ['Espaço ou recurso inválido para a igreja atual.'],
                ]);
            }
            if (! $recurso->ativo && ! in_array($id, $jaAlocados, true)) {
                throw ValidationException::withMessages([
                    'recursos' => ["{$recurso->nome} está inativo."],
                ]);
            }
            if ($recurso->isEspaco()) {
                $pedido[$id] = 1;
            }
        }

        return $pedido;
    }

    /**
     * @param  array<string, int>  $pedido
     */
    public function temEspaco(array $pedido): bool
    {
        if ($pedido === []) {
            return false;
        }

        return PlanejamentoRecurso::query()
            ->whereIn('id', array_keys($pedido))
            ->where('tipo', PlanejamentoRecurso::TIPO_ESPACO)
            ->exists();
    }

    /**
     * Lança 422 se algum item do pedido excede o disponível no intervalo.
     * Deve ser chamado dentro de transação: bloqueia as linhas dos itens.
     *
     * @param  array<string, int>  $pedido
     */
    public function assertCapacidade(
        string $anualId,
        PlanejamentoIntervalo $intervalo,
        array $pedido,
        ?string $ignorarEventoId = null,
        bool $publico = false,
    ): void {
        if ($pedido === []) {
            return;
        }

        $recursos = PlanejamentoRecurso::query()
            ->whereIn('id', array_keys($pedido))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $ocupacao = $this->ocupacao($anualId, $intervalo, array_keys($pedido), $ignorarEventoId);

        $conflitos = [];
        $mensagens = [];
        foreach ($pedido as $id => $quantidade) {
            $recurso = $recursos->get($id);
            if ($recurso === null) {
                continue;
            }
            $ocupado = $ocupacao[$id]['total'] ?? 0;
            $disponivel = max(0, $recurso->quantidade - $ocupado);
            if ($quantidade <= $disponivel) {
                continue;
            }

            $conflito = [
                'recurso_id' => $recurso->id,
                'nome' => $recurso->nome,
                'tipo' => $recurso->tipo,
                'quantidade' => $recurso->quantidade,
                'disponivel' => $disponivel,
                'pedido' => $quantidade,
            ];
            if (! $publico) {
                $conflito['eventos'] = array_map(
                    fn (PlanejamentoEvento $e) => $this->resumoEvento($e, $recurso->id),
                    $ocupacao[$id]['eventos'] ?? []
                );
            }
            $conflitos[] = $conflito;
            $mensagens[] = $recurso->isEspaco()
                ? "{$recurso->nome} já está ocupado neste horário."
                : "{$recurso->nome}: {$disponivel} de {$recurso->quantidade} livre(s) neste horário; pedido {$quantidade}.";
        }

        if ($conflitos === []) {
            return;
        }

        $exception = ValidationException::withMessages(['recursos' => $mensagens]);
        $exception->response = response()->json([
            'message' => $mensagens[0],
            'errors' => ['recursos' => $mensagens],
            'conflitos_recursos' => $conflitos,
        ], 422);

        throw $exception;
    }

    /**
     * @return list<array{recurso_id: string, nome: string, tipo: string, quantidade: int, disponivel: int}>
     */
    public function disponibilidade(string $anualId, PlanejamentoIntervalo $intervalo, ?string $ignorarEventoId = null): array
    {
        $recursos = $this->list(true);
        $ocupacao = $this->ocupacao($anualId, $intervalo, $recursos->pluck('id')->all(), $ignorarEventoId);

        return $recursos->map(fn (PlanejamentoRecurso $r) => [
            'recurso_id' => $r->id,
            'nome' => $r->nome,
            'tipo' => $r->tipo,
            'quantidade' => $r->quantidade,
            'disponivel' => max(0, $r->quantidade - ($ocupacao[$r->id]['total'] ?? 0)),
        ])->values()->all();
    }

    /**
     * Soma da alocação de outros eventos (não recusados) com intervalo sobreposto.
     *
     * @param  list<string>  $recursoIds
     * @return array<string, array{total: int, eventos: list<PlanejamentoEvento>}>
     */
    private function ocupacao(string $anualId, PlanejamentoIntervalo $intervalo, array $recursoIds, ?string $ignorarEventoId): array
    {
        if ($recursoIds === []) {
            return [];
        }

        $inicioDia = $intervalo->inicio->format('Y-m-d');
        $fimDia = $intervalo->fim->format('Y-m-d');

        $candidatos = PlanejamentoEvento::query()
            ->with(['recursos' => fn ($q) => $q->whereIn('planejamento_recursos.id', $recursoIds)])
            ->where('planejamento_anual_id', $anualId)
            ->where('status_solicitacao', '!=', PlanejamentoEvento::STATUS_RECUSADO)
            ->when($ignorarEventoId !== null, fn ($q) => $q->whereKeyNot($ignorarEventoId))
            ->whereHas('recursos', fn ($q) => $q->whereIn('planejamento_recursos.id', $recursoIds))
            ->whereDate('data_inicio', '<=', $fimDia)
            ->where(function ($q) use ($inicioDia) {
                $q->whereDate('data_fim', '>=', $inicioDia)
                    ->orWhere(fn ($q2) => $q2->whereNull('data_fim')->whereDate('data_inicio', '>=', $inicioDia));
            })
            ->get();

        $ocupacao = [];
        foreach ($candidatos as $evento) {
            if (! PlanejamentoIntervalo::deEvento($evento)->sobrepoe($intervalo)) {
                continue;
            }
            foreach ($evento->recursos as $recurso) {
                $ocupacao[$recurso->id] ??= ['total' => 0, 'eventos' => []];
                $ocupacao[$recurso->id]['total'] += (int) $recurso->pivot->quantidade;
                $ocupacao[$recurso->id]['eventos'][] = $evento;
            }
        }

        return $ocupacao;
    }

    /**
     * @return array<string, mixed>
     */
    private function resumoEvento(PlanejamentoEvento $evento, string $recursoId): array
    {
        return [
            'id' => $evento->id,
            'titulo' => $evento->titulo,
            'pastoral_id' => $evento->pastoral_id,
            'data_inicio' => $evento->data_inicio?->format('Y-m-d'),
            'data_fim' => $evento->data_fim?->format('Y-m-d'),
            'hora_inicio' => PlanejamentoIntervalo::formatHora($evento->hora_inicio),
            'hora_fim' => PlanejamentoIntervalo::formatHora($evento->hora_fim),
            'quantidade' => (int) ($evento->recursos->firstWhere('id', $recursoId)?->pivot->quantidade ?? 0),
        ];
    }

    private function normalizarQuantidade(string $tipo, mixed $quantidade): int
    {
        if ($tipo === PlanejamentoRecurso::TIPO_ESPACO) {
            return 1;
        }

        return max(1, (int) $quantidade);
    }

    private function assertNomeUnico(string $igrejaId, string $nome, ?string $ignorarId = null): void
    {
        $exists = PlanejamentoRecurso::query()
            ->where('igreja_id', $igrejaId)
            ->where('nome', $nome)
            ->when($ignorarId !== null, fn ($q) => $q->whereKeyNot($ignorarId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'nome' => ['Já existe um espaço ou recurso com este nome.'],
            ]);
        }
    }
}
