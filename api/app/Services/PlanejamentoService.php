<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CalendarioLocal;
use App\Models\Pastoral;
use App\Models\PlanejamentoAnual;
use App\Models\PlanejamentoEvento;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PlanejamentoService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly PlanejamentoAccessService $access,
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
            ->with(['locais', 'pastoral'])
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
            ->with(['locais', 'pastoral', 'planejamentoAnual'])
            ->whereKey($id)
            ->whereHas('planejamentoAnual', fn ($q) => $q->where('igreja_id', $igrejaId))
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{evento: PlanejamentoEvento, conflitos: list<array<string, mixed>>}
     */
    public function createEvento(PlanejamentoAnual $anual, array $data): array
    {
        $this->assertPastoralDaIgreja($data['pastoral_id']);
        $this->assertLocalOuTexto($data);
        $localIds = $this->resolveLocalIds($data['local_ids'] ?? []);

        $isGestor = $this->access->canGerir();
        $status = $isGestor && ! empty($data['status_solicitacao'])
            ? $data['status_solicitacao']
            : PlanejamentoEvento::STATUS_PROPOSTA;

        $evento = PlanejamentoEvento::query()->create([
            'planejamento_anual_id' => $anual->id,
            'pastoral_id' => $data['pastoral_id'],
            'titulo' => $data['titulo'],
            'data_inicio' => $data['data_inicio'],
            'data_fim' => $data['data_fim'] ?? null,
            'hora_inicio' => $data['hora_inicio'] ?? null,
            'hora_fim' => $data['hora_fim'] ?? null,
            'participantes_media' => $data['participantes_media'] ?? null,
            'recorrencia_texto' => $data['recorrencia_texto'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'local_texto' => $data['local_texto'] ?? null,
            'status_solicitacao' => $status,
            'motivo_ajuste' => $isGestor ? ($data['motivo_ajuste'] ?? null) : null,
        ]);

        if ($localIds !== []) {
            $evento->locais()->sync($localIds);
        }

        $evento->load(['locais', 'pastoral', 'planejamentoAnual']);
        $conflitos = $this->detectarConflitos($evento);

        return ['evento' => $evento, 'conflitos' => $conflitos];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{evento: PlanejamentoEvento, conflitos: list<array<string, mixed>>}
     */
    public function updateEvento(PlanejamentoEvento $evento, array $data): array
    {
        $isGestor = $this->access->canGerir();

        if (isset($data['pastoral_id'])) {
            $this->assertPastoralDaIgreja($data['pastoral_id']);
        }

        if (array_key_exists('local_ids', $data) || array_key_exists('local_texto', $data)) {
            $merged = [
                'local_ids' => $data['local_ids'] ?? $evento->locais->pluck('id')->all(),
                'local_texto' => array_key_exists('local_texto', $data)
                    ? $data['local_texto']
                    : $evento->local_texto,
            ];
            $this->assertLocalOuTexto($merged);
        }

        $fillable = [
            'pastoral_id', 'titulo', 'data_inicio', 'data_fim', 'hora_inicio', 'hora_fim',
            'participantes_media', 'recorrencia_texto', 'observacoes', 'local_texto',
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
            // Coordenador reenvia após ajuste → volta para proposta
            if ($evento->status_solicitacao === PlanejamentoEvento::STATUS_AJUSTE_SOLICITADO) {
                $evento->status_solicitacao = PlanejamentoEvento::STATUS_PROPOSTA;
                $evento->motivo_ajuste = null;
            }
        }

        $evento->save();

        if (array_key_exists('local_ids', $data)) {
            $localIds = $this->resolveLocalIds($data['local_ids'] ?? []);
            $evento->locais()->sync($localIds);
        }

        $evento->load(['locais', 'pastoral', 'planejamentoAnual']);
        $conflitos = $this->detectarConflitos($evento);

        return ['evento' => $evento->refresh()->load(['locais', 'pastoral', 'planejamentoAnual']), 'conflitos' => $conflitos];
    }

    public function deleteEvento(PlanejamentoEvento $evento): void
    {
        $evento->locais()->detach();
        $evento->delete();
    }

    public function pdf(PlanejamentoAnual $anual, ?int $mes = null): Response
    {
        if (! in_array($anual->status, [PlanejamentoAnual::STATUS_REVISAO, PlanejamentoAnual::STATUS_FECHADO], true)) {
            throw ValidationException::withMessages([
                'status' => ['PDF disponível apenas em revisão ou fechado.'],
            ]);
        }

        // PDF sempre visão completa (gestor / ver_global)
        $eventos = PlanejamentoEvento::query()
            ->with(['locais', 'pastoral'])
            ->where('planejamento_anual_id', $anual->id)
            ->when($mes !== null, function ($q) use ($anual, $mes) {
                $q->whereMonth('data_inicio', $mes)->whereYear('data_inicio', $anual->ano);
            })
            ->orderBy('data_inicio')
            ->orderBy('hora_inicio')
            ->get();

        $anual->load('igreja');
        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];

        $pdf = Pdf::loadView('planejamento.anual-pdf', [
            'anual' => $anual,
            'eventos' => $eventos,
            'mesFiltro' => $mes,
            'mesNome' => $mes !== null ? ($meses[$mes] ?? (string) $mes) : null,
            'igreja' => $anual->igreja,
        ])->setPaper('a4', 'portrait');

        $nome = 'planejamento-'.$anual->ano.($mes !== null ? '-'.str_pad((string) $mes, 2, '0', STR_PAD_LEFT) : '').'.pdf';

        return $pdf->download($nome);
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
        $data = $evento->data_inicio->format('Y-m-d');
        $hora = $this->formatHora($evento->hora_inicio) ?? '00:00';

        return Carbon::parse($data.' '.$hora.':00');
    }

    private function intervaloFim(PlanejamentoEvento $evento): Carbon
    {
        $data = ($evento->data_fim ?? $evento->data_inicio)->format('Y-m-d');
        $hora = $this->formatHora($evento->hora_fim)
            ?? $this->formatHora($evento->hora_inicio)
            ?? '23:59';

        $fim = Carbon::parse($data.' '.$hora.':00');
        // Se só tem hora_inicio sem hora_fim no mesmo dia, assume 1h
        if ($evento->hora_fim === null && $evento->hora_inicio !== null && $evento->data_fim === null) {
            $fim = $this->intervaloInicio($evento)->copy()->addHour();
        }

        return $fim;
    }

    private function formatHora(mixed $hora): ?string
    {
        if ($hora === null || $hora === '') {
            return null;
        }

        if ($hora instanceof \DateTimeInterface) {
            return $hora->format('H:i');
        }

        $str = (string) $hora;
        if (preg_match('/^(\d{2}:\d{2})/', $str, $m)) {
            return $m[1];
        }

        return $str;
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
    private function assertLocalOuTexto(array $data): void
    {
        $ids = $data['local_ids'] ?? [];
        $texto = trim((string) ($data['local_texto'] ?? ''));

        if (($ids === [] || $ids === null) && $texto === '') {
            throw ValidationException::withMessages([
                'local_ids' => ['Informe ao menos um local ou texto livre de local.'],
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
}
