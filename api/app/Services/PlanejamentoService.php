<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanejamentoAnual;
use App\Models\PlanejamentoEvento;
use App\Models\PlanejamentoPastoralToken;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PlanejamentoService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly PlanejamentoAccessService $access,
    ) {}

    public function listAnos(): Collection
    {
        return PlanejamentoAnual::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->orderByDesc('ano')
            ->get();
    }

    /**
     * @param  array{ano: int}  $data
     */
    public function createAno(array $data): PlanejamentoAnual
    {
        $igrejaId = $this->igrejaContext->current()->id;

        $exists = PlanejamentoAnual::query()
            ->where('igreja_id', $igrejaId)
            ->where('ano', $data['ano'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'ano' => ['Já existe planejamento para este ano.'],
            ]);
        }

        return PlanejamentoAnual::query()->create([
            'igreja_id' => $igrejaId,
            'ano' => $data['ano'],
            'status' => PlanejamentoAnual::STATUS_ABERTO,
            'aberto_em' => now(),
        ]);
    }

    public function findAno(string $id): PlanejamentoAnual
    {
        return PlanejamentoAnual::query()
            ->with(['eventos.pastoral'])
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    public function updateAno(PlanejamentoAnual $ano): PlanejamentoAnual
    {
        return $this->findAno($ano->id);
    }

    public function deleteAno(PlanejamentoAnual $ano): void
    {
        $ano->delete();
    }

    public function transitionStatus(PlanejamentoAnual $ano, string $status): PlanejamentoAnual
    {
        $allowed = match ($ano->status) {
            PlanejamentoAnual::STATUS_ABERTO => [PlanejamentoAnual::STATUS_COLETA],
            PlanejamentoAnual::STATUS_COLETA => [PlanejamentoAnual::STATUS_FECHADO, PlanejamentoAnual::STATUS_ABERTO],
            PlanejamentoAnual::STATUS_FECHADO => [PlanejamentoAnual::STATUS_COLETA, PlanejamentoAnual::STATUS_ABERTO],
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Transição de {$ano->status} para {$status} não permitida."],
            ]);
        }

        $ano->status = $status;

        if ($status === PlanejamentoAnual::STATUS_COLETA) {
            $ano->coleta_em = $ano->coleta_em ?? now();
            $ano->fechado_em = null;
        } elseif ($status === PlanejamentoAnual::STATUS_FECHADO) {
            $ano->fechado_em = now();
        } elseif ($status === PlanejamentoAnual::STATUS_ABERTO) {
            $ano->aberto_em = $ano->aberto_em ?? now();
            $ano->fechado_em = null;
        }

        $ano->save();

        return $this->findAno($ano->id);
    }

    /**
     * @param  array{pastoral_id?: string, status?: string, from?: string, to?: string}  $filters
     */
    public function listEventos(PlanejamentoAnual $ano, array $filters = [], ?Authenticatable $user = null): Collection
    {
        $query = PlanejamentoEvento::query()
            ->with('pastoral')
            ->where('planejamento_anual_id', $ano->id);

        $scoped = $this->access->scopedPastoralIds($user);
        if ($scoped !== null) {
            if ($scoped === []) {
                return collect();
            }
            $query->whereIn('pastoral_id', $scoped);
        }

        if (! empty($filters['pastoral_id'])) {
            $query->where('pastoral_id', $filters['pastoral_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->where('data_inicio', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('data_inicio', '<=', $filters['to'].' 23:59:59');
        }

        return $query->orderBy('data_inicio')->get();
    }

    /**
     * @param  array{pastoral_id: string, titulo: string, descricao?: ?string, data_inicio: string, data_fim?: ?string, dia_inteiro?: bool}  $data
     */
    public function createEvento(PlanejamentoAnual $ano, array $data, ?Authenticatable $user = null): PlanejamentoEvento
    {
        $this->assertAnoEditavelParaCriacao($ano, $user);
        $pastoral = $this->access->findPastoralOfIgreja($data['pastoral_id']);
        $this->assertPodeProporNaPastoral($pastoral->id, $user);
        $this->assertDataNoAno($ano, $data['data_inicio']);

        return PlanejamentoEvento::query()->create([
            'planejamento_anual_id' => $ano->id,
            'pastoral_id' => $pastoral->id,
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'data_inicio' => $data['data_inicio'],
            'data_fim' => $data['data_fim'] ?? null,
            'dia_inteiro' => $data['dia_inteiro'] ?? true,
            'status' => PlanejamentoEvento::STATUS_PROPOSTA,
            'criado_via' => PlanejamentoEvento::VIA_LOGIN,
        ])->load('pastoral');
    }

    public function findEvento(string $eventoId): PlanejamentoEvento
    {
        $evento = PlanejamentoEvento::query()
            ->with(['pastoral', 'planejamento'])
            ->whereKey($eventoId)
            ->firstOrFail();

        if ($evento->planejamento->igreja_id !== $this->igrejaContext->current()->id) {
            throw (new NotFoundHttpException);
        }

        return $evento;
    }

    /**
     * @param  array{titulo?: string, descricao?: ?string, data_inicio?: string, data_fim?: ?string, dia_inteiro?: bool, pastoral_id?: string}  $data
     */
    public function updateEvento(PlanejamentoEvento $evento, array $data, ?Authenticatable $user = null): PlanejamentoEvento
    {
        $ano = $evento->planejamento;
        $this->assertPodeEditarEvento($evento, $user);

        if (isset($data['pastoral_id']) && $data['pastoral_id'] !== $evento->pastoral_id) {
            if (! $this->access->canManage($user)) {
                throw ValidationException::withMessages([
                    'pastoral_id' => ['Sem permissão para alterar a pastoral.'],
                ]);
            }
            $this->access->findPastoralOfIgreja($data['pastoral_id']);
        }

        if (isset($data['data_inicio'])) {
            $this->assertDataNoAno($ano, $data['data_inicio']);
        }

        $evento->fill(collect($data)->only([
            'titulo', 'descricao', 'data_inicio', 'data_fim', 'dia_inteiro', 'pastoral_id',
        ])->all());
        $evento->save();

        return $evento->refresh()->load('pastoral');
    }

    /**
     * @param  array{data_inicio?: string, data_fim?: ?string, status?: string, notas_revisao?: ?string}  $data
     */
    public function patchEvento(PlanejamentoEvento $evento, array $data, ?Authenticatable $user = null): PlanejamentoEvento
    {
        $ano = $evento->planejamento;

        if ($ano->isFechado()) {
            throw ValidationException::withMessages([
                'status' => ['Planejamento fechado: somente leitura.'],
            ]);
        }

        $changingDates = array_key_exists('data_inicio', $data) || array_key_exists('data_fim', $data);
        $changingStatus = array_key_exists('status', $data) || array_key_exists('notas_revisao', $data);

        if ($changingDates || $changingStatus) {
            if (! $this->access->canManage($user)) {
                throw ValidationException::withMessages([
                    'status' => ['Somente quem gerencia o planejamento pode alterar data ou status.'],
                ]);
            }
        }

        if (isset($data['data_inicio'])) {
            $this->assertDataNoAno($ano, $data['data_inicio']);
        }

        if (isset($data['status']) && ! in_array($data['status'], PlanejamentoEvento::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['Status de evento inválido.'],
            ]);
        }

        $evento->fill(collect($data)->only([
            'data_inicio', 'data_fim', 'status', 'notas_revisao',
        ])->all());
        $evento->save();

        return $evento->refresh()->load('pastoral');
    }

    public function deleteEvento(PlanejamentoEvento $evento, ?Authenticatable $user = null): void
    {
        $this->assertPodeEditarEvento($evento, $user);
        $evento->delete();
    }

    /**
     * @param  array{expires_at?: ?string}  $data
     * @return array{tokenModel: PlanejamentoPastoralToken, token: string}
     */
    public function createOrRotateToken(PlanejamentoAnual $ano, string $pastoralId, array $data = []): array
    {
        $pastoral = $this->access->findPastoralOfIgreja($pastoralId);

        PlanejamentoPastoralToken::query()
            ->where('planejamento_anual_id', $ano->id)
            ->where('pastoral_id', $pastoral->id)
            ->whereNull('revogado_em')
            ->update(['revogado_em' => now()]);

        $plain = Str::random(48);
        $tokenModel = PlanejamentoPastoralToken::query()->create([
            'planejamento_anual_id' => $ano->id,
            'pastoral_id' => $pastoral->id,
            'token' => hash('sha256', $plain),
            'token_preview' => substr($plain, 0, 8),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return ['tokenModel' => $tokenModel, 'token' => $plain];
    }

    public function revokeToken(PlanejamentoAnual $ano, string $pastoralId): void
    {
        $pastoral = $this->access->findPastoralOfIgreja($pastoralId);

        $updated = PlanejamentoPastoralToken::query()
            ->where('planejamento_anual_id', $ano->id)
            ->where('pastoral_id', $pastoral->id)
            ->whereNull('revogado_em')
            ->update(['revogado_em' => now()]);

        if ($updated === 0) {
            throw new NotFoundHttpException('Nenhum token ativo para esta pastoral.');
        }
    }

    public function findTokenByPlain(string $plain): PlanejamentoPastoralToken
    {
        $hash = hash('sha256', $plain);
        $token = PlanejamentoPastoralToken::query()
            ->with(['planejamento', 'pastoral'])
            ->where('token', $hash)
            ->whereNull('revogado_em')
            ->first();

        if ($token === null || ! $token->isAtivo()) {
            throw new NotFoundHttpException('Link de planejamento inválido.');
        }

        return $token;
    }

    public function assertTokenPermiteEscrita(PlanejamentoPastoralToken $token): void
    {
        if (! $token->planejamento->isColeta()) {
            throw ValidationException::withMessages([
                'token' => ['Planejamento não está em coleta.'],
            ]);
        }
    }

    /**
     * @param  array{titulo: string, descricao?: ?string, data_inicio: string, data_fim?: ?string, dia_inteiro?: bool}  $data
     */
    public function createEventoPublico(PlanejamentoPastoralToken $token, array $data): PlanejamentoEvento
    {
        $this->assertTokenPermiteEscrita($token);
        $ano = $token->planejamento;
        $this->assertDataNoAno($ano, $data['data_inicio']);

        return PlanejamentoEvento::query()->create([
            'planejamento_anual_id' => $ano->id,
            'pastoral_id' => $token->pastoral_id,
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'data_inicio' => $data['data_inicio'],
            'data_fim' => $data['data_fim'] ?? null,
            'dia_inteiro' => $data['dia_inteiro'] ?? true,
            'status' => PlanejamentoEvento::STATUS_PROPOSTA,
            'criado_via' => PlanejamentoEvento::VIA_LINK,
        ])->load('pastoral');
    }

    public function listEventosPublicos(PlanejamentoPastoralToken $token): Collection
    {
        return PlanejamentoEvento::query()
            ->with('pastoral')
            ->where('planejamento_anual_id', $token->planejamento_anual_id)
            ->where('pastoral_id', $token->pastoral_id)
            ->orderBy('data_inicio')
            ->get();
    }

    public function findEventoPublico(PlanejamentoPastoralToken $token, string $eventoId): PlanejamentoEvento
    {
        $evento = PlanejamentoEvento::query()
            ->with(['pastoral', 'planejamento'])
            ->whereKey($eventoId)
            ->where('planejamento_anual_id', $token->planejamento_anual_id)
            ->where('pastoral_id', $token->pastoral_id)
            ->first();

        if ($evento === null) {
            throw new NotFoundHttpException('Evento não encontrado.');
        }

        return $evento;
    }

    /**
     * @param  array{titulo?: string, descricao?: ?string, data_inicio?: string, data_fim?: ?string, dia_inteiro?: bool}  $data
     */
    public function updateEventoPublico(PlanejamentoPastoralToken $token, PlanejamentoEvento $evento, array $data): PlanejamentoEvento
    {
        $this->assertTokenPermiteEscrita($token);

        if (! $evento->isProposta()) {
            throw ValidationException::withMessages([
                'status' => ['Somente eventos em proposta podem ser editados pelo link.'],
            ]);
        }

        if (isset($data['data_inicio'])) {
            $this->assertDataNoAno($token->planejamento, $data['data_inicio']);
        }

        $evento->fill(collect($data)->only([
            'titulo', 'descricao', 'data_inicio', 'data_fim', 'dia_inteiro',
        ])->all());
        $evento->save();

        return $evento->refresh()->load('pastoral');
    }

    public function deleteEventoPublico(PlanejamentoPastoralToken $token, PlanejamentoEvento $evento): void
    {
        $this->assertTokenPermiteEscrita($token);

        if (! $evento->isProposta()) {
            throw ValidationException::withMessages([
                'status' => ['Somente eventos em proposta podem ser excluídos pelo link.'],
            ]);
        }

        $evento->delete();
    }

    public function listPastoralUsers(string $pastoralId): Collection
    {
        $pastoral = $this->access->findPastoralOfIgreja($pastoralId);

        return $pastoral->users()->orderBy('id')->get();
    }

    /**
     * @param  array{user_id: string}  $data
     */
    public function attachPastoralUser(string $pastoralId, array $data): User
    {
        $pastoral = $this->access->findPastoralOfIgreja($pastoralId);
        $user = $this->resolveUser($data['user_id']);

        $pastoral->users()->syncWithoutDetaching([$user->id]);

        return $user;
    }

    /**
     * @param  array{user_id: string}  $data
     */
    public function detachPastoralUser(string $pastoralId, array $data): void
    {
        $pastoral = $this->access->findPastoralOfIgreja($pastoralId);
        $user = $this->resolveUser($data['user_id']);

        $pastoral->users()->detach($user->id);
    }

    private function resolveUser(string $userId): User
    {
        $user = User::query()->where('ulid', $userId)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'user_id' => ['Usuário não encontrado.'],
            ]);
        }

        return $user;
    }

    private function assertAnoEditavelParaCriacao(PlanejamentoAnual $ano, ?Authenticatable $user): void
    {
        if ($ano->isFechado()) {
            throw ValidationException::withMessages([
                'status' => ['Planejamento fechado: não é possível criar eventos.'],
            ]);
        }

        if ($this->access->canManage($user)) {
            return;
        }

        if (! $ano->isColeta()) {
            throw ValidationException::withMessages([
                'status' => ['Propostas só são aceitas com o ano em coleta.'],
            ]);
        }

        if (! $this->access->canPropose($user)) {
            throw ValidationException::withMessages([
                'status' => ['Sem permissão para propor eventos.'],
            ]);
        }
    }

    private function assertPodeProporNaPastoral(string $pastoralId, ?Authenticatable $user): void
    {
        if ($this->access->canManage($user)) {
            return;
        }

        if (! $this->access->isLinkedToPastoral($pastoralId, $user)) {
            throw ValidationException::withMessages([
                'pastoral_id' => ['Usuário não vinculado a esta pastoral.'],
            ]);
        }
    }

    private function assertPodeEditarEvento(PlanejamentoEvento $evento, ?Authenticatable $user): void
    {
        $ano = $evento->planejamento;

        if ($ano->isFechado()) {
            throw ValidationException::withMessages([
                'status' => ['Planejamento fechado: somente leitura.'],
            ]);
        }

        if ($this->access->canManage($user)) {
            return;
        }

        if (! $ano->isColeta()) {
            throw ValidationException::withMessages([
                'status' => ['Edição de propostas só é permitida com o ano em coleta.'],
            ]);
        }

        if (! $this->access->canPropose($user) || ! $this->access->isLinkedToPastoral($evento->pastoral_id, $user)) {
            throw ValidationException::withMessages([
                'pastoral_id' => ['Sem permissão para editar eventos desta pastoral.'],
            ]);
        }

        if (! $evento->isProposta()) {
            throw ValidationException::withMessages([
                'status' => ['Só é possível editar eventos em status proposta.'],
            ]);
        }
    }

    private function assertDataNoAno(PlanejamentoAnual $ano, string $dataInicio): void
    {
        $year = (int) date('Y', strtotime($dataInicio));
        if ($year !== $ano->ano) {
            throw ValidationException::withMessages([
                'data_inicio' => ["A data deve estar no ano civil {$ano->ano}."],
            ]);
        }
    }
}
