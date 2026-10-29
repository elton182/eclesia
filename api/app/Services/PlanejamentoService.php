<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CalendarioLocal;
use App\Models\Igreja;
use App\Models\Pastoral;
use App\Models\PlanejamentoAnual;
use App\Models\PlanejamentoEvento;
use App\Models\PlanejamentoPastoralToken;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PlanejamentoService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly PlanejamentoAccessService $access,
        private readonly PlanejamentoRecursoService $recursos,
        private readonly QrCodeService $qrCode,
        private readonly PlanejamentoPublicUrl $publicUrl,
    ) {}

    public function listAnuais(?int $ano = null): Collection
    {
        $query = PlanejamentoAnual::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->orderByDesc('ano');

        if ($ano !== null) {
            $query->where('ano', $ano);
        }

        return $query->get();
    }

    /**
     * @param  array{ano: int}  $data
     */
    public function createAnual(array $data): PlanejamentoAnual
    {
        $igrejaId = $this->igrejaContext->current()->id;

        $exists = PlanejamentoAnual::query()
            ->where('igreja_id', $igrejaId)
            ->where('ano', $data['ano'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'ano' => ['Já existe planejamento anual para este ano.'],
            ]);
        }

        return PlanejamentoAnual::query()->create([
            'igreja_id' => $igrejaId,
            'ano' => $data['ano'],
            'status' => PlanejamentoAnual::STATUS_RASCUNHO,
        ]);
    }

    public function findAnual(string $id): PlanejamentoAnual
    {
        return PlanejamentoAnual::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @param  array{ano?: int}  $data
     */
    public function updateAnual(PlanejamentoAnual $anual, array $data): PlanejamentoAnual
    {
        if (isset($data['ano']) && (int) $data['ano'] !== $anual->ano) {
            $exists = PlanejamentoAnual::query()
                ->where('igreja_id', $anual->igreja_id)
                ->where('ano', $data['ano'])
                ->whereKeyNot($anual->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'ano' => ['Já existe planejamento anual para este ano.'],
                ]);
            }
        }

        $anual->fill($data);
        $anual->save();

        return $anual->refresh();
    }

    public function deleteAnual(PlanejamentoAnual $anual): void
    {
        $anual->delete();
    }

    public function transitionStatus(PlanejamentoAnual $anual, string $status): PlanejamentoAnual
    {
        $allowed = match ($anual->status) {
            PlanejamentoAnual::STATUS_RASCUNHO => [PlanejamentoAnual::STATUS_COLETA],
            PlanejamentoAnual::STATUS_COLETA => [PlanejamentoAnual::STATUS_REVISAO, PlanejamentoAnual::STATUS_RASCUNHO],
            PlanejamentoAnual::STATUS_REVISAO => [PlanejamentoAnual::STATUS_FECHADO, PlanejamentoAnual::STATUS_COLETA],
            PlanejamentoAnual::STATUS_FECHADO => [PlanejamentoAnual::STATUS_REVISAO],
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Transição de {$anual->status} para {$status} não permitida."],
            ]);
        }

        $anual->status = $status;
        if ($status === PlanejamentoAnual::STATUS_FECHADO) {
            $anual->fechado_em = now();
        } elseif ($anual->fechado_em !== null) {
            $anual->fechado_em = null;
        }
        $anual->save();

        return $anual->refresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, PlanejamentoEvento>
     */
    public function listEventos(PlanejamentoAnual $anual, array $filters = []): Collection
    {
        $query = PlanejamentoEvento::query()
            ->with(['locais', 'recursos', 'pastoral'])
            ->where('planejamento_anual_id', $anual->id)
            ->orderBy('data_inicio')
            ->orderBy('hora_inicio');

        $filtroPastorais = $this->access->pastoralIdsFiltroListagem();
        if ($filtroPastorais !== null) {
            $query->whereIn('pastoral_id', $filtroPastorais->all());
        }

        if (! empty($filters['mes'])) {
            $mes = (int) $filters['mes'];
            $query->where(function ($q) use ($anual, $mes) {
                $q->whereMonth('data_inicio', $mes)->whereYear('data_inicio', $anual->ano);
            });
        }

        if (! empty($filters['pastoral_id'])) {
            $query->where('pastoral_id', $filters['pastoral_id']);
        }

        if (! empty($filters['status_solicitacao'])) {
            $query->where('status_solicitacao', $filters['status_solicitacao']);
        }

        if (! empty($filters['local_id'])) {
            $localId = $filters['local_id'];
            $query->whereHas('locais', fn ($q) => $q->where('calendario_locais.id', $localId));
        }

        return $query->get();
    }

    public function findEvento(string $id): PlanejamentoEvento
    {
        $igrejaId = $this->igrejaContext->current()->id;

        return PlanejamentoEvento::query()
            ->with(['locais', 'recursos', 'pastoral', 'planejamentoAnual'])
            ->whereKey($id)
            ->whereHas('planejamentoAnual', fn ($q) => $q->where('igreja_id', $igrejaId))
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{evento: PlanejamentoEvento, conflitos: list<array<string, mixed>>}
     */
    public function createEvento(PlanejamentoAnual $anual, array $data, bool $publico = false): array
    {
        $this->assertPastoralDaIgreja($data['pastoral_id']);
        $pedido = $this->recursos->resolverPedido($data['recursos'] ?? []);
        $this->assertLocalOuTexto($data, $this->recursos->temEspaco($pedido));
        $localIds = $this->resolveLocalIds($data['local_ids'] ?? []);

        $isGestor = $this->access->canGerir();
        $status = $isGestor && ! empty($data['status_solicitacao'])
            ? $data['status_solicitacao']
            : PlanejamentoEvento::STATUS_PROPOSTA;

        $evento = DB::transaction(function () use ($anual, $data, $pedido, $localIds, $isGestor, $status, $publico) {
            if ($status !== PlanejamentoEvento::STATUS_RECUSADO) {
                $this->recursos->assertCapacidade(
                    $anual->id,
                    PlanejamentoIntervalo::deDados(
                        (string) $data['data_inicio'],
                        $data['data_fim'] ?? null,
                        $data['hora_inicio'] ?? null,
                        $data['hora_fim'] ?? null,
                    ),
                    $pedido,
                    null,
                    $publico,
                );
            }

            $evento = PlanejamentoEvento::query()->create([
                'planejamento_anual_id' => $anual->id,
                'pastoral_id' => $data['pastoral_id'],
                'titulo' => $data['titulo'],
                'data_inicio' => $data['data_inicio'],
                'data_fim' => $data['data_fim'] ?? null,
                'hora_inicio' => $data['hora_inicio'] ?? null,
                'hora_fim' => $data['hora_fim'] ?? null,
                'participantes_media' => $data['participantes_media'] ?? null,
                'observacoes' => $data['observacoes'] ?? null,
                'local_texto' => $data['local_texto'] ?? null,
                'status_solicitacao' => $status,
                'motivo_ajuste' => $isGestor ? ($data['motivo_ajuste'] ?? null) : null,
            ]);

            if ($localIds !== []) {
                $evento->locais()->sync($localIds);
            }
            $evento->recursos()->sync($this->pivotRecursos($pedido));

            return $evento;
        });

        $evento->load(['locais', 'recursos', 'pastoral', 'planejamentoAnual']);
        $conflitos = $this->detectarConflitos($evento);

        return ['evento' => $evento, 'conflitos' => $conflitos];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{evento: PlanejamentoEvento, conflitos: list<array<string, mixed>>}
     */
    /**
     * @param  array<string, mixed>  $data
     * @return array{evento: PlanejamentoEvento, conflitos: list<array<string, mixed>>}
     */
    public function updateEvento(PlanejamentoEvento $evento, array $data, bool $publico = false): array
    {
        $isGestor = ! $publico && $this->access->canGerir();

        if (isset($data['pastoral_id'])) {
            $this->assertPastoralDaIgreja($data['pastoral_id']);
        }

        $alocadosAtuais = $evento->recursos
            ->mapWithKeys(fn ($r) => [$r->id => (int) $r->pivot->quantidade])
            ->all();
        $recursosAlterados = array_key_exists('recursos', $data);
        $pedido = $recursosAlterados
            ? $this->recursos->resolverPedido($data['recursos'] ?? [], array_keys($alocadosAtuais))
            : $alocadosAtuais;

        if ($recursosAlterados || array_key_exists('local_ids', $data) || array_key_exists('local_texto', $data)) {
            $merged = [
                'local_ids' => $data['local_ids'] ?? $evento->locais->pluck('id')->all(),
                'local_texto' => array_key_exists('local_texto', $data)
                    ? $data['local_texto']
                    : $evento->local_texto,
            ];
            $this->assertLocalOuTexto($merged, $this->recursos->temEspaco($pedido));
        }

        $statusAnterior = $evento->status_solicitacao;

        $fillable = [
            'pastoral_id', 'titulo', 'data_inicio', 'data_fim', 'hora_inicio', 'hora_fim',
            'participantes_media', 'observacoes', 'local_texto',
        ];

        foreach ($fillable as $key) {
            if (array_key_exists($key, $data)) {
                $evento->{$key} = $data[$key];
            }
        }

        if ($isGestor) {
            if (array_key_exists('status_solicitacao', $data)) {
                $evento->status_solicitacao = $data['status_solicitacao'];
            }
            if (array_key_exists('motivo_ajuste', $data)) {
                $evento->motivo_ajuste = $data['motivo_ajuste'];
            }
        } else {
            // Coordenador / link público reenvia após ajuste → volta para proposta
            if ($evento->status_solicitacao === PlanejamentoEvento::STATUS_AJUSTE_SOLICITADO) {
                $evento->status_solicitacao = PlanejamentoEvento::STATUS_PROPOSTA;
                $evento->motivo_ajuste = null;
            }
        }

        $intervaloAlterado = $evento->isDirty(['data_inicio', 'data_fim', 'hora_inicio', 'hora_fim']);
        $saiuDeRecusado = $statusAnterior === PlanejamentoEvento::STATUS_RECUSADO
            && $evento->status_solicitacao !== PlanejamentoEvento::STATUS_RECUSADO;
        $deveValidar = $evento->status_solicitacao !== PlanejamentoEvento::STATUS_RECUSADO
            && ($recursosAlterados || $intervaloAlterado || $saiuDeRecusado);

        DB::transaction(function () use ($evento, $data, $pedido, $recursosAlterados, $deveValidar, $publico) {
            if ($deveValidar) {
                $this->recursos->assertCapacidade(
                    $evento->planejamento_anual_id,
                    PlanejamentoIntervalo::deEvento($evento),
                    $pedido,
                    $evento->id,
                    $publico,
                );
            }

            $evento->save();

            if (array_key_exists('local_ids', $data)) {
                $localIds = $this->resolveLocalIds($data['local_ids'] ?? []);
                $evento->locais()->sync($localIds);
            }

            if ($recursosAlterados) {
                $evento->recursos()->sync($this->pivotRecursos($pedido));
            }
        });

        $evento->load(['locais', 'recursos', 'pastoral', 'planejamentoAnual']);
        $conflitos = $this->detectarConflitos($evento);

        return ['evento' => $evento->refresh()->load(['locais', 'recursos', 'pastoral', 'planejamentoAnual']), 'conflitos' => $conflitos];
    }

    /**
     * @param  array<string, int>  $pedido
     * @return array<string, array{quantidade: int}>
     */
    private function pivotRecursos(array $pedido): array
    {
        return collect($pedido)->map(fn (int $q) => ['quantidade' => $q])->all();
    }

    public function deleteEvento(PlanejamentoEvento $evento): void
    {
        $evento->locais()->detach();
        $evento->recursos()->detach();
        $evento->delete();
    }

    /**
     * @param  'agenda'|'links'  $tipo
     */
    public function pdf(PlanejamentoAnual $anual, string $tipo, ?int $mes = null): Response
    {
        $this->assertPdfDisponivel($anual);

        return match ($tipo) {
            'links' => $this->pdfLinksPastorais($anual),
            default => $this->pdfAgendaMes($anual, $mes),
        };
    }

    private function assertPdfDisponivel(PlanejamentoAnual $anual): void
    {
        if (! in_array($anual->status, [
            PlanejamentoAnual::STATUS_COLETA,
            PlanejamentoAnual::STATUS_REVISAO,
            PlanejamentoAnual::STATUS_FECHADO,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['PDF disponível a partir da coleta (também em revisão ou fechado).'],
            ]);
        }
    }

    /**
     * Agenda do mês selecionado (eventos em ordem cronológica).
     */
    public function pdfAgendaMes(PlanejamentoAnual $anual, ?int $mes): Response
    {
        $this->assertPdfDisponivel($anual);

        if ($mes === null || $mes < 1 || $mes > 12) {
            throw ValidationException::withMessages([
                'mes' => ['Informe o mês (1–12) para a agenda.'],
            ]);
        }

        $igreja = $this->igrejaContext->current();
        $anual->loadMissing('igreja');

        $eventos = PlanejamentoEvento::query()
            ->with(['locais', 'recursos', 'pastoral'])
            ->where('planejamento_anual_id', $anual->id)
            ->whereMonth('data_inicio', $mes)
            ->whereYear('data_inicio', $anual->ano)
            ->orderBy('data_inicio')
            ->orderBy('hora_inicio')
            ->get();

        $meses = $this->nomesMeses();
        $mesNome = $meses[$mes] ?? (string) $mes;

        $pdf = Pdf::loadView('planejamento.anual-pdf-agenda', [
            'anual' => $anual,
            'eventos' => $eventos,
            'mesFiltro' => $mes,
            'mesNome' => $mesNome,
            'igreja' => $anual->igreja ?? $igreja,
        ])->setPaper('a4', 'portrait');

        $nome = 'planejamento-agenda-'.$anual->ano.'-'.str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'.pdf';

        return $pdf->download($nome);
    }

    /**
     * Tabela Pastoral | QR + link. Cria links faltantes das pastorais ativas.
     */
    public function pdfLinksPastorais(PlanejamentoAnual $anual): Response
    {
        $this->assertPdfDisponivel($anual);

        $igreja = $this->igrejaContext->current();
        $anual->loadMissing('igreja');

        $pastorais = Pastoral::query()
            ->where('igreja_id', $igreja->id)
            ->where('ativa', true)
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();

        $linhas = [];
        foreach ($pastorais as $pastoral) {
            $result = $this->ensurePastoralPublicLink($anual, $pastoral->id);
            $url = $this->publicUrl->forToken($result['plain']);
            $linhas[] = [
                'pastoral' => $pastoral,
                'link' => $url,
                'qr_data_uri' => $this->qrCode->dataUriPng($url, 3),
            ];
        }

        $pdf = Pdf::loadView('planejamento.anual-pdf-links', [
            'anual' => $anual,
            'linhas' => $linhas,
            'igreja' => $anual->igreja ?? $igreja,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('planejamento-links-'.$anual->ano.'.pdf');
    }

    /**
     * @return array<int, string>
     */
    private function nomesMeses(): array
    {
        return [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
    }

    /**
     * Garante link público da pastoral no ano: reutiliza o ativo ou cria se estiver faltando/revogado.
     * Não rotaciona um link ativo existente.
     *
     * @return array{token: PlanejamentoPastoralToken, plain: string}
     */
    private function ensurePastoralPublicLink(PlanejamentoAnual $anual, string $pastoralId): array
    {
        $existente = PlanejamentoPastoralToken::query()
            ->where('planejamento_anual_id', $anual->id)
            ->where('pastoral_id', $pastoralId)
            ->whereNull('revogado_em')
            ->first();

        if ($existente !== null && $existente->isAtivo() && $existente->token_encrypted !== null) {
            $plain = (string) $existente->token_encrypted;
            $existente->setAttribute('plain_token', $plain);

            return ['token' => $existente, 'plain' => $plain];
        }

        return $this->createOrRotatePastoralToken($anual, $pastoralId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function detectarConflitos(PlanejamentoEvento $evento): array
    {
        $localIds = $evento->locais->pluck('id')->all();
        if ($localIds === []) {
            return [];
        }

        $inicio = $this->intervaloInicio($evento);
        $fim = $this->intervaloFim($evento);

        $candidatos = PlanejamentoEvento::query()
            ->with('locais')
            ->where('planejamento_anual_id', $evento->planejamento_anual_id)
            ->whereKeyNot($evento->id)
            ->whereHas('locais', fn ($q) => $q->whereIn('calendario_locais.id', $localIds))
            ->get();

        $conflitos = [];
        foreach ($candidatos as $outro) {
            $outroInicio = $this->intervaloInicio($outro);
            $outroFim = $this->intervaloFim($outro);
            if ($inicio < $outroFim && $outroInicio < $fim) {
                $shared = $outro->locais->pluck('id')->intersect($localIds)->values()->all();
                $conflitos[] = [
                    'id' => $outro->id,
                    'titulo' => $outro->titulo,
                    'pastoral_id' => $outro->pastoral_id,
                    'data_inicio' => $outro->data_inicio?->format('Y-m-d'),
                    'data_fim' => $outro->data_fim?->format('Y-m-d'),
                    'hora_inicio' => $this->formatHora($outro->hora_inicio),
                    'hora_fim' => $this->formatHora($outro->hora_fim),
                    'local_ids' => $shared,
                ];
            }
        }

        return $conflitos;
    }

    private function intervaloInicio(PlanejamentoEvento $evento): Carbon
    {
        return PlanejamentoIntervalo::deEvento($evento)->inicio;
    }

    private function intervaloFim(PlanejamentoEvento $evento): Carbon
    {
        return PlanejamentoIntervalo::deEvento($evento)->fim;
    }

    private function formatHora(mixed $hora): ?string
    {
        return PlanejamentoIntervalo::formatHora($hora);
    }

    private function assertPastoralDaIgreja(string $pastoralId): void
    {
        $exists = Pastoral::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->whereKey($pastoralId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'pastoral_id' => ['Pastoral inválida para a igreja atual.'],
            ]);
        }
    }

    /**
     * @param  array{local_ids?: list<string>|null, local_texto?: ?string}  $data
     */
    private function assertLocalOuTexto(array $data, bool $temEspaco = false): void
    {
        $ids = $data['local_ids'] ?? [];
        $texto = trim((string) ($data['local_texto'] ?? ''));

        if (! $temEspaco && ($ids === [] || $ids === null) && $texto === '') {
            throw ValidationException::withMessages([
                'local_ids' => ['Informe ao menos um espaço, local ou texto livre de local.'],
            ]);
        }
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function resolveLocalIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $igrejaId = $this->igrejaContext->current()->id;
        $found = CalendarioLocal::query()
            ->where('igreja_id', $igrejaId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($found) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'local_ids' => ['Um ou mais locais são inválidos.'],
            ]);
        }

        return $found;
    }

    /**
     * Gera ou rotaciona o link tokenizado da pastoral no ano.
     *
     * @return array{token: PlanejamentoPastoralToken, plain: string}
     */
    public function createOrRotatePastoralToken(PlanejamentoAnual $anual, string $pastoralId): array
    {
        $this->assertPastoralDaIgreja($pastoralId);

        $plain = Str::random(48);
        $hash = hash('sha256', $plain);

        $token = PlanejamentoPastoralToken::query()->updateOrCreate(
            [
                'planejamento_anual_id' => $anual->id,
                'pastoral_id' => $pastoralId,
            ],
            [
                'token_hash' => $hash,
                'token_preview' => substr($plain, 0, 8),
                'token_encrypted' => $plain,
                'expires_at' => null,
                'revogado_em' => null,
            ]
        );

        $token->refresh();
        $token->setAttribute('plain_token', $plain);

        return ['token' => $token, 'plain' => $plain];
    }

    /**
     * @return Collection<int, PlanejamentoPastoralToken>
     */
    public function listPastoralTokens(PlanejamentoAnual $anual): Collection
    {
        return PlanejamentoPastoralToken::query()
            ->where('planejamento_anual_id', $anual->id)
            ->orderBy('created_at')
            ->get()
            ->each(function (PlanejamentoPastoralToken $token) {
                if ($token->isAtivo() && $token->token_encrypted !== null) {
                    $token->setAttribute('plain_token', $token->token_encrypted);
                }
            });
    }

    public function revokePastoralToken(PlanejamentoAnual $anual, string $pastoralId): void
    {
        PlanejamentoPastoralToken::query()
            ->where('planejamento_anual_id', $anual->id)
            ->where('pastoral_id', $pastoralId)
            ->whereNull('revogado_em')
            ->update(['revogado_em' => now()]);
    }

    public function findTokenByPlain(string $plain): PlanejamentoPastoralToken
    {
        $hash = hash('sha256', $plain);

        $token = PlanejamentoPastoralToken::query()
            ->with(['planejamento', 'pastoral'])
            ->where('token_hash', $hash)
            ->first();

        if ($token === null || ! $token->isAtivo()) {
            throw ValidationException::withMessages([
                'token' => ['Link inválido, expirado ou revogado.'],
            ]);
        }

        return $token;
    }

    /**
     * @return Collection<int, PlanejamentoEvento>
     */
    public function listEventosPublicos(PlanejamentoPastoralToken $token): Collection
    {
        return PlanejamentoEvento::query()
            ->with(['locais', 'recursos', 'pastoral'])
            ->where('planejamento_anual_id', $token->planejamento_anual_id)
            ->where('pastoral_id', $token->pastoral_id)
            ->orderBy('data_inicio')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{evento: PlanejamentoEvento, conflitos: list<array<string, mixed>>}
     */
    public function createEventoPublico(PlanejamentoPastoralToken $token, array $data): array
    {
        $anual = $this->anualDoToken($token);

        if ($anual->status !== PlanejamentoAnual::STATUS_COLETA) {
            throw ValidationException::withMessages([
                'status' => ['A coleta deste ano não está aberta.'],
            ]);
        }

        $this->igrejaContext->set(Igreja::query()->findOrFail($anual->igreja_id));

        return $this->createEvento($anual, [
            ...$data,
            'pastoral_id' => $token->pastoral_id,
            'local_ids' => [],
            'local_texto' => $data['local_texto'] ?? null,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{evento: PlanejamentoEvento, conflitos: list<array<string, mixed>>}
     */
    public function updateEventoPublico(PlanejamentoPastoralToken $token, string $eventoId, array $data): array
    {
        $anual = $this->anualDoToken($token);
        $this->igrejaContext->set(Igreja::query()->findOrFail($anual->igreja_id));

        $evento = $this->eventoDoToken($token, $eventoId);
        $this->assertPodeMutarEventoPublico($anual, $evento);

        unset($data['pastoral_id'], $data['status_solicitacao'], $data['motivo_ajuste']);

        return $this->updateEvento($evento, [
            ...$data,
            'local_ids' => $data['local_ids'] ?? [],
        ], true);
    }

    public function deleteEventoPublico(PlanejamentoPastoralToken $token, string $eventoId): void
    {
        $anual = $this->anualDoToken($token);
        $this->igrejaContext->set(Igreja::query()->findOrFail($anual->igreja_id));

        $evento = $this->eventoDoToken($token, $eventoId);
        $this->assertPodeMutarEventoPublico($anual, $evento);
        $this->deleteEvento($evento);
    }

    /**
     * @param  array{data_inicio: string, data_fim?: ?string, hora_inicio?: ?string, hora_fim?: ?string, ignorar_evento_id?: ?string}  $params
     * @return list<array<string, mixed>>
     */
    public function disponibilidadePublica(PlanejamentoPastoralToken $token, array $params): array
    {
        $anual = $this->anualDoToken($token);
        $this->igrejaContext->set(Igreja::query()->findOrFail($anual->igreja_id));

        $ignorar = $params['ignorar_evento_id'] ?? null;
        if (is_string($ignorar) && $ignorar !== '') {
            // Só ignora se o evento pertencer à pastoral do token (evita vazar capacidade alheia)
            $this->eventoDoToken($token, $ignorar);
        } else {
            $ignorar = null;
        }

        return $this->recursos->disponibilidade(
            $anual->id,
            PlanejamentoIntervalo::deDados(
                $params['data_inicio'],
                $params['data_fim'] ?? null,
                $params['hora_inicio'] ?? null,
                $params['hora_fim'] ?? null,
            ),
            $ignorar,
        );
    }

    private function anualDoToken(PlanejamentoPastoralToken $token): PlanejamentoAnual
    {
        $anual = $token->planejamento;
        if ($anual === null) {
            $anual = PlanejamentoAnual::query()->findOrFail($token->planejamento_anual_id);
        }

        return $anual;
    }

    private function eventoDoToken(PlanejamentoPastoralToken $token, string $eventoId): PlanejamentoEvento
    {
        $evento = PlanejamentoEvento::query()
            ->with(['locais', 'recursos', 'pastoral', 'planejamentoAnual'])
            ->whereKey($eventoId)
            ->where('planejamento_anual_id', $token->planejamento_anual_id)
            ->where('pastoral_id', $token->pastoral_id)
            ->first();

        if ($evento === null) {
            abort(404);
        }

        return $evento;
    }

    private function assertPodeMutarEventoPublico(PlanejamentoAnual $anual, PlanejamentoEvento $evento): void
    {
        if ($anual->status === PlanejamentoAnual::STATUS_COLETA) {
            return;
        }

        if (
            $anual->status === PlanejamentoAnual::STATUS_REVISAO
            && $evento->status_solicitacao === PlanejamentoEvento::STATUS_AJUSTE_SOLICITADO
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => ['Não é possível alterar esta proposta neste momento.'],
        ]);
    }
}
