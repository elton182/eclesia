<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Casal;
use App\Models\EscalaAtribuicao;
use App\Models\EscalaEquipe;
use App\Models\EscalaOcorrencia;
use App\Models\EscalaTipo;
use App\Models\EventoAgenda;
use App\Models\Pessoa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EscalaService
{
    public function __construct(private readonly IgrejaContext $igrejaContext) {}

    private function igrejaId(): string
    {
        return $this->igrejaContext->current()->id;
    }

    /**
     * @return Collection<int, EscalaTipo>
     */
    public function listTipos(): Collection
    {
        return EscalaTipo::query()
            ->where('igreja_id', $this->igrejaId())
            ->withCount(['equipes', 'ocorrencias'])
            ->orderBy('nome')
            ->get();
    }

    public function findTipo(string $id): EscalaTipo
    {
        return EscalaTipo::query()
            ->where('igreja_id', $this->igrejaId())
            ->with(['equipes' => fn ($q) => $q->orderBy('ordem')->orderBy('nome')])
            ->withCount(['equipes', 'ocorrencias'])
            ->findOrFail($id);
    }

    /**
     * @param  array{nome: string, descricao?: string|null, unidade_preferida?: string, recorrencia?: string}  $data
     */
    public function createTipo(array $data): EscalaTipo
    {
        return EscalaTipo::query()->create([
            'igreja_id' => $this->igrejaId(),
            'nome' => trim($data['nome']),
            'descricao' => $data['descricao'] ?? null,
            'unidade_preferida' => $data['unidade_preferida'] ?? 'ambos',
            'recorrencia' => $data['recorrencia'] ?? 'avulsa',
        ]);
    }

    /**
     * @param  array{nome?: string, descricao?: string|null, unidade_preferida?: string, recorrencia?: string}  $data
     */
    public function updateTipo(EscalaTipo $tipo, array $data): EscalaTipo
    {
        if (isset($data['nome'])) {
            $data['nome'] = trim($data['nome']);
        }
        $tipo->update($data);

        return $this->findTipo($tipo->id);
    }

    public function deleteTipo(EscalaTipo $tipo): void
    {
        $tipo->delete();
    }

    /**
     * @return Collection<int, EscalaEquipe>
     */
    public function listEquipes(string $tipoId): Collection
    {
        $this->findTipo($tipoId);

        return EscalaEquipe::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('escala_tipo_id', $tipoId)
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();
    }

    /**
     * @param  array{nome: string, cor?: string|null, ordem?: int, vagas_sugeridas?: int|null}  $data
     */
    public function createEquipe(string $tipoId, array $data): EscalaEquipe
    {
        $this->findTipo($tipoId);

        return EscalaEquipe::query()->create([
            'igreja_id' => $this->igrejaId(),
            'escala_tipo_id' => $tipoId,
            'nome' => trim($data['nome']),
            'cor' => $data['cor'] ?? null,
            'ordem' => $data['ordem'] ?? 0,
            'vagas_sugeridas' => $data['vagas_sugeridas'] ?? null,
        ]);
    }

    public function findEquipe(string $id): EscalaEquipe
    {
        return EscalaEquipe::query()
            ->where('igreja_id', $this->igrejaId())
            ->findOrFail($id);
    }

    /**
     * @param  array{nome?: string, cor?: string|null, ordem?: int, vagas_sugeridas?: int|null}  $data
     */
    public function updateEquipe(EscalaEquipe $equipe, array $data): EscalaEquipe
    {
        if (isset($data['nome'])) {
            $data['nome'] = trim($data['nome']);
        }
        $equipe->update($data);

        return $equipe->refresh();
    }

    public function deleteEquipe(EscalaEquipe $equipe): void
    {
        $equipe->delete();
    }

    /**
     * @return Collection<int, EscalaOcorrencia>
     */
    public function listOcorrencias(string $tipoId): Collection
    {
        $this->findTipo($tipoId);

        return EscalaOcorrencia::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('escala_tipo_id', $tipoId)
            ->with('tipo')
            ->orderBy('inicia_em')
            ->get();
    }

    public function findOcorrencia(string $id): EscalaOcorrencia
    {
        return EscalaOcorrencia::query()
            ->where('igreja_id', $this->igrejaId())
            ->with([
                'tipo',
                'atribuicoes.equipe',
                'atribuicoes.pessoa',
                'atribuicoes.casal.pessoaA',
                'atribuicoes.casal.pessoaB',
            ])
            ->findOrFail($id);
    }

    /**
     * @param  array{titulo?: string|null, inicia_em: string, termina_em?: string|null, local?: string|null}  $data
     */
    public function createOcorrencia(string $tipoId, array $data): EscalaOcorrencia
    {
        $tipo = $this->findTipo($tipoId);

        return DB::transaction(function () use ($tipo, $data) {
            $ocorrencia = EscalaOcorrencia::query()->create([
                'igreja_id' => $this->igrejaId(),
                'escala_tipo_id' => $tipo->id,
                'titulo' => $data['titulo'] ?? null,
                'inicia_em' => $data['inicia_em'],
                'termina_em' => $data['termina_em'] ?? null,
                'local' => $data['local'] ?? null,
            ]);

            $this->syncEventoAgenda($ocorrencia, $tipo);

            return $this->findOcorrencia($ocorrencia->id);
        });
    }

    /**
     * @param  array{inicio: string, fim: string, frequencia: string, local?: string|null, titulo?: string|null}  $data
     * @return Collection<int, EscalaOcorrencia>
     */
    public function gerarOcorrencias(string $tipoId, array $data): Collection
    {
        $tipo = $this->findTipo($tipoId);
        $inicio = Carbon::parse($data['inicio']);
        $fim = Carbon::parse($data['fim']);
        if ($fim->lt($inicio)) {
            throw ValidationException::withMessages([
                'fim' => ['A data fim deve ser posterior ou igual ao início.'],
            ]);
        }

        $cursor = $inicio->copy();
        $created = [];

        DB::transaction(function () use ($tipo, $data, $cursor, $fim, &$created) {
            $guard = 0;
            while ($cursor->lte($fim) && $guard < 366) {
                $ocorrencia = EscalaOcorrencia::query()->create([
                    'igreja_id' => $this->igrejaId(),
                    'escala_tipo_id' => $tipo->id,
                    'titulo' => $data['titulo'] ?? null,
                    'inicia_em' => $cursor->toDateTimeString(),
                    'termina_em' => null,
                    'local' => $data['local'] ?? null,
                ]);
                $this->syncEventoAgenda($ocorrencia, $tipo);
                $created[] = $ocorrencia->id;

                if (($data['frequencia'] ?? '') === 'mensal') {
                    $cursor->addMonth();
                } else {
                    $cursor->addWeek();
                }
                $guard++;
            }
        });

        return EscalaOcorrencia::query()
            ->whereIn('id', $created)
            ->with('tipo')
            ->orderBy('inicia_em')
            ->get();
    }

    /**
     * @param  array{titulo?: string|null, inicia_em?: string, termina_em?: string|null, local?: string|null}  $data
     */
    public function updateOcorrencia(EscalaOcorrencia $ocorrencia, array $data): EscalaOcorrencia
    {
        return DB::transaction(function () use ($ocorrencia, $data) {
            $ocorrencia->update($data);
            $ocorrencia->refresh();
            $this->syncEventoAgenda($ocorrencia, $ocorrencia->tipo ?? $this->findTipo($ocorrencia->escala_tipo_id));

            return $this->findOcorrencia($ocorrencia->id);
        });
    }

    public function deleteOcorrencia(EscalaOcorrencia $ocorrencia): void
    {
        DB::transaction(function () use ($ocorrencia) {
            $eventoId = $ocorrencia->evento_agenda_id;
            $ocorrencia->delete();
            if ($eventoId) {
                EventoAgenda::query()
                    ->where('igreja_id', $this->igrejaId())
                    ->whereKey($eventoId)
                    ->delete();
            }
        });
    }

    /**
     * @param  array{escala_equipe_id: string, pessoa_id?: string|null, casal_id?: string|null}  $data
     */
    public function createAtribuicao(string $ocorrenciaId, array $data): EscalaAtribuicao
    {
        $ocorrencia = $this->findOcorrencia($ocorrenciaId);
        $pessoaId = $data['pessoa_id'] ?? null;
        $casalId = $data['casal_id'] ?? null;

        $hasPessoa = is_string($pessoaId) && $pessoaId !== '';
        $hasCasal = is_string($casalId) && $casalId !== '';

        if ($hasPessoa === $hasCasal) {
            throw ValidationException::withMessages([
                'pessoa_id' => ['Informe exatamente um de pessoa_id ou casal_id.'],
            ]);
        }

        $equipe = $this->findEquipe($data['escala_equipe_id']);
        if ($equipe->escala_tipo_id !== $ocorrencia->escala_tipo_id) {
            throw ValidationException::withMessages([
                'escala_equipe_id' => ['A equipe não pertence ao tipo desta ocorrência.'],
            ]);
        }

        if ($hasPessoa) {
            Pessoa::query()
                ->where('igreja_id', $this->igrejaId())
                ->whereKey($pessoaId)
                ->firstOrFail();
        } else {
            Casal::query()
                ->where('igreja_id', $this->igrejaId())
                ->whereKey($casalId)
                ->firstOrFail();
        }

        $dup = EscalaAtribuicao::query()
            ->where('escala_ocorrencia_id', $ocorrencia->id)
            ->where('escala_equipe_id', $equipe->id)
            ->when($hasPessoa, fn ($q) => $q->where('pessoa_id', $pessoaId))
            ->when($hasCasal, fn ($q) => $q->where('casal_id', $casalId))
            ->exists();

        if ($dup) {
            throw ValidationException::withMessages([
                'escala_equipe_id' => ['Esta pessoa/casal já está escalado(a) nesta equipe nesta ocorrência.'],
            ]);
        }

        return EscalaAtribuicao::query()->create([
            'igreja_id' => $this->igrejaId(),
            'escala_ocorrencia_id' => $ocorrencia->id,
            'escala_equipe_id' => $equipe->id,
            'pessoa_id' => $hasPessoa ? $pessoaId : null,
            'casal_id' => $hasCasal ? $casalId : null,
        ])->load(['equipe', 'pessoa', 'casal.pessoaA', 'casal.pessoaB']);
    }

    public function findAtribuicao(string $id): EscalaAtribuicao
    {
        return EscalaAtribuicao::query()
            ->where('igreja_id', $this->igrejaId())
            ->findOrFail($id);
    }

    public function deleteAtribuicao(EscalaAtribuicao $atribuicao): void
    {
        $atribuicao->delete();
    }

    /**
     * @return Collection<int, EscalaOcorrencia>
     */
    public function agenda(?string $from, ?string $to, ?string $tipoId): Collection
    {
        $query = EscalaOcorrencia::query()
            ->where('igreja_id', $this->igrejaId())
            ->with('tipo')
            ->orderBy('inicia_em');

        if ($from) {
            $query->where('inicia_em', '>=', Carbon::parse($from));
        }
        if ($to) {
            $query->where('inicia_em', '<=', Carbon::parse($to));
        }
        if ($tipoId) {
            $query->where('escala_tipo_id', $tipoId);
        }

        return $query->get();
    }

    /**
     * @return array{pessoas: list<array{id: string, nome: string}>, casais: list<array{id: string, rotulo: string}>}
     */
    public function candidatos(?string $q = null): array
    {
        $igrejaId = $this->igrejaId();
        $q = $q !== null ? trim($q) : '';

        $pessoas = Pessoa::query()
            ->where('igreja_id', $igrejaId)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (Pessoa $p) => ['id' => $p->id, 'nome' => $p->nome])
            ->values()
            ->all();

        if ($q !== '') {
            $lower = mb_strtolower($q);
            $pessoas = array_values(array_filter(
                $pessoas,
                static fn (array $p): bool => str_contains(mb_strtolower($p['nome']), $lower)
            ));
        }

        $casais = Casal::query()
            ->where('igreja_id', $igrejaId)
            ->with(['pessoaA', 'pessoaB'])
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(function (Casal $c) {
                $a = $c->pessoaA?->nome ?? '';
                $b = $c->pessoaB?->nome ?? '';

                return [
                    'id' => $c->id,
                    'rotulo' => trim($a.' e '.$b, ' e'),
                ];
            })
            ->values()
            ->all();

        if ($q !== '') {
            $lower = mb_strtolower($q);
            $casais = array_values(array_filter(
                $casais,
                static fn (array $c): bool => str_contains(mb_strtolower($c['rotulo']), $lower)
            ));
        }

        return [
            'pessoas' => array_slice($pessoas, 0, 40),
            'casais' => array_slice($casais, 0, 40),
        ];
    }

    private function syncEventoAgenda(EscalaOcorrencia $ocorrencia, EscalaTipo $tipo): void
    {
        $titulo = $ocorrencia->titulo ?: $tipo->nome;
        $payload = [
            'igreja_id' => $this->igrejaId(),
            'titulo' => $titulo,
            'inicia_em' => $ocorrencia->inicia_em,
            'termina_em' => $ocorrencia->termina_em,
            'local' => $ocorrencia->local,
            'dono_modulo' => 'escalas',
            'tipo' => 'escala',
            'referencia_tipo' => 'escala_ocorrencia',
            'referencia_id' => $ocorrencia->id,
        ];

        if ($ocorrencia->evento_agenda_id) {
            EventoAgenda::query()
                ->where('igreja_id', $this->igrejaId())
                ->whereKey($ocorrencia->evento_agenda_id)
                ->update($payload);

            return;
        }

        $evento = EventoAgenda::query()->create($payload);
        $ocorrencia->update(['evento_agenda_id' => $evento->id]);
    }
}
