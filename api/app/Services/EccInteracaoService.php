<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EccComentario;
use App\Models\EccLike;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EccInteracaoService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly EccVisibilityScope $visibility,
    ) {}

    /**
     * @return array{likes_count: int, liked_by_me: bool, comentarios_count: int}
     */
    public function statsFor(string $alvoTipo, string $alvoId, ?User $user = null): array
    {
        $user ??= auth()->user() instanceof User ? auth()->user() : null;
        $igrejaId = $this->igrejaId();

        $likesCount = EccLike::query()
            ->where('igreja_id', $igrejaId)
            ->where('alvo_tipo', $alvoTipo)
            ->where('alvo_id', $alvoId)
            ->count();

        $likedByMe = false;
        if ($user instanceof User) {
            $likedByMe = EccLike::query()
                ->where('igreja_id', $igrejaId)
                ->where('alvo_tipo', $alvoTipo)
                ->where('alvo_id', $alvoId)
                ->where('user_id', $user->id)
                ->exists();
        }

        $comentariosCount = EccComentario::query()
            ->where('igreja_id', $igrejaId)
            ->where('alvo_tipo', $alvoTipo)
            ->where('alvo_id', $alvoId)
            ->where('status', EccComentario::STATUS_APROVADO)
            ->count();

        return [
            'likes_count' => $likesCount,
            'liked_by_me' => $likedByMe,
            'comentarios_count' => $comentariosCount,
        ];
    }

    /**
     * @return array{liked: bool, likes_count: int}
     */
    public function like(string $alvoTipo, string $alvoId, User $user): array
    {
        $this->assertCanInteract($alvoTipo, $alvoId);

        EccLike::query()->firstOrCreate([
            'igreja_id' => $this->igrejaId(),
            'alvo_tipo' => $alvoTipo,
            'alvo_id' => $alvoId,
            'user_id' => $user->id,
        ]);

        return $this->likeState($alvoTipo, $alvoId, true);
    }

    /**
     * @return array{liked: bool, likes_count: int}
     */
    public function unlike(string $alvoTipo, string $alvoId, User $user): array
    {
        $this->assertCanInteract($alvoTipo, $alvoId);

        EccLike::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('alvo_tipo', $alvoTipo)
            ->where('alvo_id', $alvoId)
            ->where('user_id', $user->id)
            ->delete();

        return $this->likeState($alvoTipo, $alvoId, false);
    }

    /**
     * @return array{liked: bool, likes_count: int}
     */
    public function toggleLike(string $alvoTipo, string $alvoId, User $user): array
    {
        $this->assertCanInteract($alvoTipo, $alvoId);

        $exists = EccLike::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('alvo_tipo', $alvoTipo)
            ->where('alvo_id', $alvoId)
            ->where('user_id', $user->id)
            ->exists();

        return $exists
            ? $this->unlike($alvoTipo, $alvoId, $user)
            : $this->like($alvoTipo, $alvoId, $user);
    }

    /**
     * @return array{liked: bool, likes_count: int}
     */
    private function likeState(string $alvoTipo, string $alvoId, bool $liked): array
    {
        $count = EccLike::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('alvo_tipo', $alvoTipo)
            ->where('alvo_id', $alvoId)
            ->count();

        return ['liked' => $liked, 'likes_count' => $count];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listComentarios(string $alvoTipo, string $alvoId, ?User $user = null): array
    {
        $this->assertCanInteract($alvoTipo, $alvoId);
        $user ??= auth()->user() instanceof User ? auth()->user() : null;

        $query = EccComentario::query()
            ->with('user')
            ->where('igreja_id', $this->igrejaId())
            ->where('alvo_tipo', $alvoTipo)
            ->where('alvo_id', $alvoId)
            ->orderBy('created_at');

        if ($this->canModerar()) {
            // moderador vê todos do alvo
        } elseif ($user instanceof User) {
            $uid = $user->id;
            $query->where(function ($q) use ($uid): void {
                $q->where('status', EccComentario::STATUS_APROVADO)
                    ->orWhere(function ($own) use ($uid): void {
                        $own->where('user_id', $uid)
                            ->whereIn('status', [
                                EccComentario::STATUS_PENDENTE,
                                EccComentario::STATUS_APROVADO,
                            ]);
                    });
            });
        } else {
            $query->where('status', EccComentario::STATUS_APROVADO);
        }

        return $query->get()
            ->map(fn (EccComentario $c): array => $this->serializeComentario($c))
            ->values()
            ->all();
    }

    /**
     * @param  array{status?: string, alvo_tipo?: string, alvo_id?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function listComentariosModeracao(array $filters = []): array
    {
        $this->assertCanModerar();

        $query = EccComentario::query()
            ->with('user')
            ->where('igreja_id', $this->igrejaId())
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', EccComentario::STATUS_PENDENTE);
        }

        if (! empty($filters['alvo_tipo'])) {
            $query->where('alvo_tipo', $filters['alvo_tipo']);
        }
        if (! empty($filters['alvo_id'])) {
            $query->where('alvo_id', $filters['alvo_id']);
        }

        return $query->get()
            ->map(fn (EccComentario $c): array => $this->serializeComentario($c))
            ->values()
            ->all();
    }

    public function createComentario(string $alvoTipo, string $alvoId, string $corpo, User $user): EccComentario
    {
        $this->assertCanInteract($alvoTipo, $alvoId);

        $corpo = trim($corpo);
        if ($corpo === '' || mb_strlen($corpo) > 2000) {
            throw ValidationException::withMessages([
                'corpo' => ['Informe um comentário de até 2000 caracteres.'],
            ]);
        }

        $status = $this->canModerar()
            ? EccComentario::STATUS_APROVADO
            : EccComentario::STATUS_PENDENTE;

        $comentario = EccComentario::query()->create([
            'igreja_id' => $this->igrejaId(),
            'alvo_tipo' => $alvoTipo,
            'alvo_id' => $alvoId,
            'user_id' => $user->id,
            'corpo' => $corpo,
            'status' => $status,
            'revisado_por_user_id' => $status === EccComentario::STATUS_APROVADO ? $user->id : null,
            'revisado_em' => $status === EccComentario::STATUS_APROVADO ? now() : null,
        ]);

        return $comentario->load('user');
    }

    public function aprovar(string $id, User $revisor): EccComentario
    {
        return $this->revisar($id, $revisor, EccComentario::STATUS_APROVADO);
    }

    public function rejeitar(string $id, User $revisor, ?string $motivo = null): EccComentario
    {
        return $this->revisar($id, $revisor, EccComentario::STATUS_REJEITADO, $motivo);
    }

    public function ocultar(string $id, User $revisor): EccComentario
    {
        return $this->revisar($id, $revisor, EccComentario::STATUS_OCULTO);
    }

    public function deleteComentario(string $id, User $actor): void
    {
        $comentario = $this->findComentario($id);

        $isOwner = (int) $comentario->user_id === (int) $actor->id;
        if (! $this->canModerar() && ! $isOwner) {
            abort(403);
        }

        if ($isOwner && ! $this->canModerar()) {
            if (! in_array($comentario->status, [
                EccComentario::STATUS_PENDENTE,
                EccComentario::STATUS_APROVADO,
            ], true)) {
                abort(403);
            }
        }

        $comentario->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeComentario(EccComentario $c): array
    {
        return [
            'id' => (string) $c->id,
            'alvo_tipo' => (string) $c->alvo_tipo,
            'alvo_id' => (string) $c->alvo_id,
            'corpo' => (string) $c->corpo,
            'status' => (string) $c->status,
            'user_id' => (int) $c->user_id,
            'autor_nome' => $c->user?->name,
            'motivo_revisao' => $c->motivo_revisao,
            'revisado_em' => $c->revisado_em?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }

    private function revisar(string $id, User $revisor, string $status, ?string $motivo = null): EccComentario
    {
        $this->assertCanModerar();
        $comentario = $this->findComentario($id);

        $comentario->status = $status;
        $comentario->revisado_por_user_id = $revisor->id;
        $comentario->revisado_em = now();
        $comentario->motivo_revisao = $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null;
        $comentario->save();

        return $comentario->fresh(['user']) ?? $comentario->load('user');
    }

    private function findComentario(string $id): EccComentario
    {
        return EccComentario::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($id)
            ->firstOrFail();
    }

    private function assertCanInteract(string $alvoTipo, string $alvoId): void
    {
        $this->resolveAlvoVisivel($alvoTipo, $alvoId);
    }

    private function resolveAlvoVisivel(string $alvoTipo, string $alvoId): void
    {
        // Resolve sob demanda para reutilizar regras de feed sem DI circular no construtor.
        if ($alvoTipo === EccComentario::ALVO_RECADO) {
            app(EccRecadoService::class)->find($alvoId);

            return;
        }

        if ($alvoTipo === EccComentario::ALVO_REFLEXAO) {
            app(EccReflexaoService::class)->find($alvoId);

            return;
        }

        abort(422, 'Tipo de alvo inválido.');
    }

    private function canModerar(): bool
    {
        return $this->visibility->userCan('ecc.interacoes.moderar')
            || $this->visibility->userCan('ecc.recados.manage')
            || $this->visibility->userCan('ecc.reflexoes.manage');
    }

    private function assertCanModerar(): void
    {
        abort_unless($this->canModerar(), 403);
    }

    private function igrejaId(): string
    {
        return (string) $this->igrejaContext->current()->id;
    }
}
