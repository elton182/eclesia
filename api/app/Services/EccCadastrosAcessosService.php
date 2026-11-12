<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Casal;
use App\Models\EccEquipe;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EccCadastrosAcessosService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
    ) {}

    /**
     * @return array{
     *   resumo: array<string, int>,
     *   equipes_acessos: list<array<string, mixed>>,
     *   quem_atualizou: list<array<string, mixed>>,
     *   atualizacoes_recentes: list<array<string, mixed>>,
     *   casais_estagnados: list<array<string, mixed>>
     * }
     */
    public function relatorio(int $janelaDias = 30): array
    {
        $janelaDias = max(7, min(365, $janelaDias));
        $igrejaId = $this->igrejaContext->current()->getKey();
        $desde = now()->subDays($janelaDias);

        $equipes = $this->equipesAcessos($igrejaId, $desde);
        $casaisEstagnados = $this->casaisEstagnados($igrejaId, $desde);
        $atualizacoes = $this->atualizacoesRecentes($igrejaId, 40);
        $quemAtualizou = $this->quemAtualizou($igrejaId, $desde);

        $casaisTotal = Casal::query()->where('igreja_id', $igrejaId)->count();
        $casaisAtualizadosJanela = (int) AuditLog::query()
            ->where('igreja_id', $igrejaId)
            ->where('action', AuditLogger::ACTION_UPDATED)
            ->where('auditable_type', Casal::class)
            ->where('created_at', '>=', $desde)
            ->distinct()
            ->count('auditable_id');

        $statusCounts = $equipes->countBy(static fn (array $row): string => $row['status_acesso']);

        return [
            'resumo' => [
                'janela_dias' => $janelaDias,
                'casais_total' => $casaisTotal,
                'casais_atualizados_janela' => $casaisAtualizadosJanela,
                'casais_estagnados' => $casaisEstagnados->count(),
                'equipes_total' => $equipes->count(),
                'equipes_com_acesso' => $equipes->where('tem_acesso', true)->count(),
                'equipes_sem_acesso' => (int) ($statusCounts['sem_acesso'] ?? 0),
                'equipes_nunca_acessaram' => (int) ($statusCounts['nunca_acessou'] ?? 0),
                'equipes_inativas_janela' => (int) ($statusCounts['inativo'] ?? 0)
                    + (int) ($statusCounts['nunca_acessou'] ?? 0),
            ],
            'equipes_acessos' => $equipes->values()->all(),
            'quem_atualizou' => $quemAtualizou,
            'atualizacoes_recentes' => $atualizacoes,
            'casais_estagnados' => $casaisEstagnados->take(50)->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function equipesAcessos(string $igrejaId, CarbonInterface $desde): Collection
    {
        $equipes = EccEquipe::query()
            ->where('igreja_id', $igrejaId)
            ->with(['acessoUser:id,ulid,name,username,must_change_password,last_login_at'])
            ->withCount('casais')
            ->orderBy('nome')
            ->get();

        $acessoUserIds = $equipes
            ->pluck('acesso_user_id')
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $fallbackLogins = $this->ultimosLoginsPorUsuario($acessoUserIds);

        return $equipes->map(function (EccEquipe $equipe) use ($desde, $fallbackLogins): array {
            $acesso = $equipe->acessoUser;
            $temAcesso = $equipe->acesso_user_id !== null && $acesso !== null;

            $ultimo = null;
            if ($temAcesso) {
                $ultimo = $acesso->last_login_at;
                if ($ultimo === null) {
                    $fallback = $fallbackLogins[(int) $acesso->getKey()] ?? null;
                    $ultimo = $fallback;
                }
            }

            $status = $this->statusAcesso($temAcesso, $ultimo, $desde);

            return [
                'id' => $equipe->id,
                'nome' => $equipe->nome,
                'cor' => $equipe->cor,
                'casais_count' => (int) $equipe->casais_count,
                'tem_acesso' => $temAcesso,
                'acesso_username' => $acesso?->username,
                'must_change_password' => $temAcesso ? (bool) $acesso->must_change_password : null,
                'ultimo_acesso_em' => $ultimo?->toIso8601String(),
                'status_acesso' => $status,
            ];
        })->sortBy(static function (array $row): array {
            $ordem = match ($row['status_acesso']) {
                'nunca_acessou' => 0,
                'inativo' => 1,
                'sem_acesso' => 2,
                default => 3,
            };

            return [$ordem, mb_strtolower((string) $row['nome'])];
        })->values();
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, CarbonInterface>
     */
    private function ultimosLoginsPorUsuario(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = AuditLog::query()
            ->where('action', AuditLogger::ACTION_LOGIN_SUCCESS)
            ->whereIn('actor_user_id', $userIds)
            ->select('actor_user_id', DB::raw('MAX(created_at) as ultimo'))
            ->groupBy('actor_user_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->actor_user_id;
            $out[$id] = Carbon::parse($row->ultimo);
        }

        return $out;
    }

    private function statusAcesso(bool $temAcesso, ?CarbonInterface $ultimo, CarbonInterface $desde): string
    {
        if (! $temAcesso) {
            return 'sem_acesso';
        }
        if ($ultimo === null) {
            return 'nunca_acessou';
        }
        if ($ultimo->lt($desde)) {
            return 'inativo';
        }

        return 'ativo';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quemAtualizou(string $igrejaId, CarbonInterface $desde): array
    {
        $actorIds = AuditLog::query()
            ->where('igreja_id', $igrejaId)
            ->where('action', AuditLogger::ACTION_UPDATED)
            ->where('auditable_type', Casal::class)
            ->where('created_at', '>=', $desde)
            ->whereNotNull('actor_user_id')
            ->select('actor_user_id')
            ->distinct()
            ->pluck('actor_user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $usersById = User::query()
            ->whereIn('id', $actorIds)
            ->get(['id', 'ulid', 'name'])
            ->keyBy('id');

        $rows = AuditLog::query()
            ->where('igreja_id', $igrejaId)
            ->where('action', AuditLogger::ACTION_UPDATED)
            ->where('auditable_type', Casal::class)
            ->where('created_at', '>=', $desde)
            ->select(
                'actor_user_id',
                DB::raw('COUNT(*) as atualizacoes_count'),
                DB::raw('MAX(created_at) as ultima_em'),
                DB::raw('MAX(actor_label) as actor_label'),
            )
            ->groupBy('actor_user_id')
            ->orderByDesc('atualizacoes_count')
            ->orderByDesc('ultima_em')
            ->limit(40)
            ->get();

        return $rows->map(function (AuditLog $row) use ($usersById): array {
            $user = $row->actor_user_id !== null
                ? $usersById->get((int) $row->actor_user_id)
                : null;

            return [
                'ator_user_id' => $user?->ulid,
                'ator_label' => $row->actor_label ?: ($user?->name),
                'atualizacoes_count' => (int) $row->atualizacoes_count,
                'ultima_em' => $row->ultima_em
                    ? Carbon::parse($row->ultima_em)->toIso8601String()
                    : null,
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function atualizacoesRecentes(string $igrejaId, int $limit): array
    {
        $logs = AuditLog::query()
            ->where('igreja_id', $igrejaId)
            ->whereIn('action', [AuditLogger::ACTION_CREATED, AuditLogger::ACTION_UPDATED])
            ->where('auditable_type', Casal::class)
            ->with('actor:id,ulid,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $casalIds = $logs
            ->pluck('auditable_ulid')
            ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();

        $rotulos = Casal::query()
            ->whereIn('id', $casalIds)
            ->with(['pessoaA:id,nome', 'pessoaB:id,nome'])
            ->get()
            ->mapWithKeys(static fn (Casal $casal): array => [$casal->id => $casal->rotuloExibicao()]);

        return $logs->map(static function (AuditLog $log) use ($rotulos): array {
            $casalId = is_string($log->auditable_ulid) ? $log->auditable_ulid : null;
            $stored = is_string($log->auditable_label) ? $log->auditable_label : null;
            $resolved = $casalId !== null ? ($rotulos[$casalId] ?? null) : null;
            $label = $resolved
                ?? (self::pareceUlidOuVazio($stored, $casalId) ? null : $stored)
                ?? $casalId
                ?? 'Casal';

            return [
                'em' => $log->created_at?->toIso8601String(),
                'acao' => $log->action,
                'ator_label' => $log->actor_label ?: $log->actor?->name,
                'ator_user_id' => $log->actor?->ulid,
                'casal_id' => $casalId,
                'casal_label' => $label,
            ];
        })->values()->all();
    }

    private static function pareceUlidOuVazio(?string $label, ?string $casalId): bool
    {
        if ($label === null || $label === '') {
            return true;
        }
        if ($casalId !== null && $label === $casalId) {
            return true;
        }

        return (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $label);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function casaisEstagnados(string $igrejaId, CarbonInterface $desde): Collection
    {
        $casais = Casal::query()
            ->where('igreja_id', $igrejaId)
            ->where('updated_at', '<', $desde)
            ->with(['pessoaA:id,nome', 'pessoaB:id,nome', 'equipe:id,nome'])
            ->orderBy('updated_at')
            ->limit(80)
            ->get();

        return $casais->map(static function (Casal $casal): array {
            $ref = $casal->updated_at ?? $casal->created_at ?? now();

            return [
                'id' => $casal->id,
                'label' => $casal->rotuloExibicao(),
                'equipe_id' => $casal->ecc_equipe_id,
                'equipe_nome' => $casal->equipe?->nome,
                'atualizado_em' => $casal->updated_at?->toIso8601String(),
                'criado_em' => $casal->created_at?->toIso8601String(),
                'dias_sem_atualizacao' => (int) $ref->diffInDays(now()),
            ];
        });
    }
}
