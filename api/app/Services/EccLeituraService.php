<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EccComentario;
use App\Models\EccLeitura;
use App\Models\EccLike;
use App\Models\User;
use Illuminate\Support\Collection;

class EccLeituraService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly EccVisibilityScope $visibility,
    ) {}

    public function marcarLido(string $alvoTipo, string $alvoId, User $user): EccLeitura
    {
        if ($alvoTipo === EccLeitura::ALVO_RECADO) {
            app(EccRecadoService::class)->find($alvoId);
        } elseif ($alvoTipo === EccLeitura::ALVO_REFLEXAO) {
            app(EccReflexaoService::class)->find($alvoId);
        } else {
            abort(422, 'Tipo inválido.');
        }

        $leitura = EccLeitura::query()->updateOrCreate(
            [
                'igreja_id' => $this->igrejaId(),
                'alvo_tipo' => $alvoTipo,
                'alvo_id' => $alvoId,
                'user_id' => $user->id,
            ],
            [
                'lido_em' => now(),
            ],
        );

        return $leitura->fresh() ?? $leitura;
    }

    /**
     * @return array{lido_por_mim: bool, lido_em: ?string}
     */
    public function leituraDoUsuario(string $alvoTipo, string $alvoId, ?User $user): array
    {
        if (! $user instanceof User) {
            return ['lido_por_mim' => false, 'lido_em' => null];
        }

        $leitura = EccLeitura::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('alvo_tipo', $alvoTipo)
            ->where('alvo_id', $alvoId)
            ->where('user_id', $user->id)
            ->first();

        return [
            'lido_por_mim' => $leitura !== null,
            'lido_em' => $leitura?->lido_em?->toIso8601String(),
        ];
    }

    public function countLeituras(string $alvoTipo, string $alvoId): int
    {
        return EccLeitura::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('alvo_tipo', $alvoTipo)
            ->where('alvo_id', $alvoId)
            ->count();
    }

    /**
     * @return array{recados: int, reflexoes: int}
     */
    public function naoLidos(?User $user): array
    {
        if (! $user instanceof User) {
            return ['recados' => 0, 'reflexoes' => 0];
        }

        $recados = 0;
        $reflexoes = 0;

        if ($this->visibility->userCan('ecc.recados.view') || $this->visibility->userCan('ecc.recados.manage')) {
            $ids = collect(app(EccRecadoService::class)->listFeedIdsForUnread());
            $lidos = $this->lidosIds(EccLeitura::ALVO_RECADO, $ids->all(), $user);
            $recados = $ids->reject(fn (string $id): bool => isset($lidos[$id]))->count();
        }

        if ($this->visibility->userCan('ecc.reflexoes.view') || $this->visibility->userCan('ecc.reflexoes.manage')) {
            $ids = collect(app(EccReflexaoService::class)->listFeedIdsForUnread());
            $lidos = $this->lidosIds(EccLeitura::ALVO_REFLEXAO, $ids->all(), $user);
            $reflexoes = $ids->reject(fn (string $id): bool => isset($lidos[$id]))->count();
        }

        return ['recados' => $recados, 'reflexoes' => $reflexoes];
    }

    /**
     * @return array<string, mixed>
     */
    public function engajamento(string $alvoTipo, string $alvoId): array
    {
        abort_unless(
            $this->visibility->userCan('ecc.interacoes.moderar')
                || $this->visibility->userCan('ecc.recados.manage')
                || $this->visibility->userCan('ecc.reflexoes.manage'),
            403,
        );

        if ($alvoTipo === EccLeitura::ALVO_RECADO) {
            app(EccRecadoService::class)->find($alvoId);
        } else {
            app(EccReflexaoService::class)->find($alvoId);
        }

        $igrejaId = $this->igrejaId();

        $leituras = EccLeitura::query()
            ->with('user')
            ->where('igreja_id', $igrejaId)
            ->where('alvo_tipo', $alvoTipo)
            ->where('alvo_id', $alvoId)
            ->orderByDesc('lido_em')
            ->get()
            ->map(fn (EccLeitura $l): array => [
                'user_id' => (int) $l->user_id,
                'nome' => $l->user?->name,
                'lido_em' => $l->lido_em?->toIso8601String(),
            ])
            ->values()
            ->all();

        $likes = EccLike::query()
            ->with('user')
            ->where('igreja_id', $igrejaId)
            ->where('alvo_tipo', $alvoTipo)
            ->where('alvo_id', $alvoId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EccLike $like): array => [
                'user_id' => (int) $like->user_id,
                'nome' => $like->user?->name,
                'em' => $like->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $comentarios = EccComentario::query()
            ->with('user')
            ->where('igreja_id', $igrejaId)
            ->where('alvo_tipo', $alvoTipo)
            ->where('alvo_id', $alvoId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EccComentario $c): array => [
                'id' => (string) $c->id,
                'user_id' => (int) $c->user_id,
                'nome' => $c->user?->name,
                'corpo' => (string) $c->corpo,
                'status' => (string) $c->status,
                'em' => $c->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'resumo' => [
                'leituras_count' => count($leituras),
                'likes_count' => count($likes),
                'comentarios_count' => count($comentarios),
                'comentarios_aprovados_count' => collect($comentarios)
                    ->where('status', EccComentario::STATUS_APROVADO)
                    ->count(),
            ],
            'leituras' => $leituras,
            'likes' => $likes,
            'comentarios' => $comentarios,
        ];
    }

    public static function resumoTexto(string $texto, int $max = 160): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
        if (mb_strlen($plain) <= $max) {
            return $plain;
        }

        return rtrim(mb_substr($plain, 0, $max - 1)).'…';
    }

    /**
     * @param  list<string>  $alvoIds
     * @return array<string, true>
     */
    private function lidosIds(string $alvoTipo, array $alvoIds, User $user): array
    {
        if ($alvoIds === []) {
            return [];
        }

        /** @var Collection<int, string> $ids */
        $ids = EccLeitura::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('alvo_tipo', $alvoTipo)
            ->where('user_id', $user->id)
            ->whereIn('alvo_id', $alvoIds)
            ->pluck('alvo_id');

        $map = [];
        foreach ($ids as $id) {
            $map[(string) $id] = true;
        }

        return $map;
    }

    private function igrejaId(): string
    {
        return (string) $this->igrejaContext->current()->id;
    }
}
