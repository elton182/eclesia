<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EccEquipe;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Validation\ValidationException;

/**
 * Simulação de visão de líder de equipe para admins (SPEC-019).
 * Estado em Cache — auth tenant evita sessão web.
 */
class ViewAsLiderService
{
    public const MODE = 'lider-equipe';

    /** @var list<string> — espelha o seed do papel `lider-equipe`. */
    public const LIDER_PERMISSIONS = [
        'telas.equipes',
        'telas.casais',
        'ecc.equipes.view',
        'ecc.casais.view',
        'ecc.casais.atualizar',
        'ecc.financeiro.depositos.submeter',
    ];

    private const CACHE_TTL_SECONDS = 43200; // 12h

    public function __construct(private readonly IgrejaContext $igrejaContext) {}

    public function canActivate(User $user): bool
    {
        $previous = getPermissionsTeamId();

        try {
            setPermissionsTeamId(null);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            if ($user->hasRole('admin-tenant')) {
                return true;
            }

            try {
                setPermissionsTeamId($this->igrejaContext->current()->id);
            } catch (\Throwable) {
                return false;
            }

            $user->unsetRelation('roles')->unsetRelation('permissions');

            return $user->hasRole('admin-igreja');
        } finally {
            setPermissionsTeamId($previous);
        }
    }

    /**
     * @param  list<string>  $equipeIds
     * @return array{igreja_id: string, equipe_ids: list<string>}
     */
    public function activate(User $user, array $equipeIds): array
    {
        if (! $this->canActivate($user)) {
            abort(403, 'Sem permissão para simular visão de líder.');
        }

        $igrejaId = $this->igrejaContext->current()->id;
        $normalized = array_values(array_unique(array_map(
            static fn (mixed $id): string => (string) $id,
            $equipeIds
        )));

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'equipe_ids' => ['Selecione ao menos uma equipe.'],
            ]);
        }

        $count = EccEquipe::query()
            ->where('igreja_id', $igrejaId)
            ->whereIn('id', $normalized)
            ->count();

        if ($count !== count($normalized)) {
            throw ValidationException::withMessages([
                'equipe_ids' => ['Uma ou mais equipes são inválidas para a igreja atual.'],
            ]);
        }

        $payload = [
            'igreja_id' => $igrejaId,
            'equipe_ids' => $normalized,
        ];

        $this->cache()->put($this->cacheKey($user), $payload, self::CACHE_TTL_SECONDS);

        return $payload;
    }

    public function clear(?User $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        $this->cache()->forget($this->cacheKey($user));
    }

    /**
     * Estado bruto do cache, ou null. Limpa se igreja atual ≠ igreja da simulação.
     *
     * @return array{igreja_id: string, equipe_ids: list<string>}|null
     */
    public function current(?User $user = null): ?array
    {
        $user ??= auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        $raw = $this->cache()->get($this->cacheKey($user));
        if (! is_array($raw) || ! isset($raw['igreja_id'], $raw['equipe_ids']) || ! is_array($raw['equipe_ids'])) {
            return null;
        }

        try {
            $igrejaId = $this->igrejaContext->current()->id;
        } catch (\Throwable) {
            // Sem igreja no contexto ainda: trata como inativo sem apagar o cache
            // (ex.: logout). A limpeza por troca de igreja só ocorre com igreja definida.
            return null;
        }

        if ((string) $raw['igreja_id'] !== (string) $igrejaId) {
            $this->clear($user);

            return null;
        }

        /** @var list<string> $ids */
        $ids = array_values(array_map(static fn (mixed $id): string => (string) $id, $raw['equipe_ids']));

        return [
            'igreja_id' => (string) $raw['igreja_id'],
            'equipe_ids' => $ids,
        ];
    }

    public function isActive(?User $user = null): bool
    {
        return $this->current($user) !== null;
    }

    /**
     * @return list<string>|null
     */
    public function restrictedEquipeIds(?User $user = null): ?array
    {
        $state = $this->current($user);
        if ($state === null) {
            return null;
        }

        return $state['equipe_ids'];
    }

    public function allowsPermission(string $permission): bool
    {
        return in_array($permission, self::LIDER_PERMISSIONS, true);
    }

    /**
     * Payload `view_as` para /me, ou null.
     *
     * @return array{mode: string, equipe_ids: list<string>, equipes: list<array{id: string, nome: string}>}|null
     */
    public function viewAsPayload(?User $user = null): ?array
    {
        $state = $this->current($user);
        if ($state === null) {
            return null;
        }

        $equipes = EccEquipe::query()
            ->whereIn('id', $state['equipe_ids'])
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(static fn (EccEquipe $e): array => [
                'id' => (string) $e->id,
                'nome' => (string) $e->nome,
            ])
            ->values()
            ->all();

        return [
            'mode' => self::MODE,
            'equipe_ids' => $state['equipe_ids'],
            'equipes' => $equipes,
        ];
    }

    private function cacheKey(User $user): string
    {
        $tenantId = tenant('id') ?? 'central';

        return 'view_as_lider:'.$tenantId.':'.$user->getKey();
    }

    /**
     * Store em arquivo, sem tags do CacheTenancyBootstrapper.
     *
     * - Cache::put tagueado some no tenancy->end() do AuthenticateTenantApi.
     * - store('array') também: TenantCacheManager novo a cada init recria ArrayStore vazio.
     * - file persiste no disco (path tenant-aware) e sobrevive end/init.
     */
    private function cache(): CacheRepository
    {
        return app('cache')->store('file');
    }
}
