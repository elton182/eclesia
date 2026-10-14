<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EccEquipe;
use App\Models\EccFinanceiroConta;
use App\Models\EccFinanceiroDeposito;
use App\Models\EccFinanceiroLancamento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EccFinanceiroDepositoService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly EccFinanceiroService $financeiro,
        private readonly EccVisibilityScope $visibility,
    ) {}

    /**
     * @param  array{status?: string, competencia?: string, equipe_id?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $this->assertCanList();

        $query = EccFinanceiroDeposito::query()
            ->with(['equipe', 'conta'])
            ->where('igreja_id', $this->igrejaId())
            ->orderByDesc('competencia')
            ->orderByDesc('created_at');

        $restricted = $this->restrictedEquipeIdsForDepositos();
        if ($restricted !== null) {
            $query->whereIn('ecc_equipe_id', $restricted === [] ? ['__none__'] : $restricted);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['competencia'])) {
            $query->whereDate('competencia', $this->parseCompetencia($filters['competencia']));
        }

        if (! empty($filters['equipe_id'])) {
            $this->assertCanAccessEquipe($filters['equipe_id']);
            $query->where('ecc_equipe_id', $filters['equipe_id']);
        }

        return $query->get()
            ->map(fn (EccFinanceiroDeposito $d): array => $this->serialize($d))
            ->values()
            ->all();
    }

    public function find(string $id): EccFinanceiroDeposito
    {
        $deposito = EccFinanceiroDeposito::query()
            ->with(['equipe', 'conta'])
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($id)
            ->firstOrFail();

        $this->assertCanAccessEquipe($deposito->ecc_equipe_id);

        return $deposito;
    }

    /**
     * @param  array{equipe_id: string, competencia: string, valor: float|int|string, conta_id?: string}  $data
     */
    public function create(array $data, UploadedFile $comprovante, User $user): EccFinanceiroDeposito
    {
        $this->assertCanSubmeter();
        $this->assertCanAccessEquipe($data['equipe_id']);

        $equipe = $this->findEquipe($data['equipe_id']);
        $competencia = $this->parseCompetencia($data['competencia']);
        $this->assertCompetenciaLivre($equipe->id, $competencia);

        $conta = $this->resolveConta($data['conta_id'] ?? null);
        $path = $this->storeComprovante($comprovante);
        $valor = round((float) $data['valor'], 2);

        try {
            return DB::transaction(function () use ($equipe, $competencia, $conta, $path, $valor, $user): EccFinanceiroDeposito {
                $lancamento = EccFinanceiroLancamento::query()->create([
                    'igreja_id' => $this->igrejaId(),
                    'ecc_financeiro_conta_id' => $conta->id,
                    'data' => $competencia->copy()->endOfMonth()->format('Y-m-d'),
                    'historico' => $this->historico($equipe, $competencia),
                    'tipo' => EccFinanceiroLancamento::TIPO_ENTRADA,
                    'valor' => $valor,
                    'transferencia_id' => null,
                    'abertura' => false,
                    'status' => EccFinanceiroLancamento::STATUS_PROVISIONADO,
                ]);

                return EccFinanceiroDeposito::query()->create([
                    'igreja_id' => $this->igrejaId(),
                    'ecc_equipe_id' => $equipe->id,
                    'competencia' => $competencia->format('Y-m-d'),
                    'valor' => $valor,
                    'ecc_financeiro_conta_id' => $conta->id,
                    'ecc_financeiro_lancamento_id' => $lancamento->id,
                    'comprovante_path' => $path,
                    'status' => EccFinanceiroDeposito::STATUS_PROVISIONADO,
                    'submetido_por_user_id' => $user->id,
                ])->load(['equipe', 'conta']);
            });
        } catch (\Throwable $e) {
            $this->deleteFile($path);
            throw $e;
        }
    }

    /**
     * @param  array{valor?: float|int|string, conta_id?: string}  $data
     */
    public function update(EccFinanceiroDeposito $deposito, array $data, ?UploadedFile $comprovante = null): EccFinanceiroDeposito
    {
        $this->assertCanSubmeterOrManage();
        $this->assertCanAccessEquipe($deposito->ecc_equipe_id);
        $this->assertProvisionado($deposito);

        $conta = isset($data['conta_id'])
            ? $this->resolveConta($data['conta_id'])
            : $deposito->conta;

        $valor = isset($data['valor'])
            ? round((float) $data['valor'], 2)
            : (float) $deposito->valor;

        $oldPath = $deposito->comprovante_path;
        $newPath = $comprovante !== null ? $this->storeComprovante($comprovante) : null;

        try {
            return DB::transaction(function () use ($deposito, $conta, $valor, $newPath, $oldPath): EccFinanceiroDeposito {
                $deposito->valor = $valor;
                $deposito->ecc_financeiro_conta_id = $conta->id;
                if ($newPath !== null) {
                    $deposito->comprovante_path = $newPath;
                }
                $deposito->save();

                $lancamento = $deposito->lancamento;
                if ($lancamento !== null) {
                    $lancamento->valor = $valor;
                    $lancamento->ecc_financeiro_conta_id = $conta->id;
                    $lancamento->save();
                }

                if ($newPath !== null) {
                    $this->deleteFile($oldPath);
                }

                return $deposito->refresh()->load(['equipe', 'conta']);
            });
        } catch (\Throwable $e) {
            if ($newPath !== null) {
                $this->deleteFile($newPath);
            }
            throw $e;
        }
    }

    public function aprovar(EccFinanceiroDeposito $deposito, User $revisor): EccFinanceiroDeposito
    {
        abort_unless($this->visibility->userCan('ecc.financeiro.manage'), 403);
        $this->assertProvisionado($deposito);

        return DB::transaction(function () use ($deposito, $revisor): EccFinanceiroDeposito {
            $deposito->status = EccFinanceiroDeposito::STATUS_CONFIRMADO;
            $deposito->motivo_rejeicao = null;
            $deposito->revisado_por_user_id = $revisor->id;
            $deposito->revisado_em = now();
            $deposito->save();

            $lancamento = $deposito->lancamento;
            if ($lancamento !== null) {
                $lancamento->status = EccFinanceiroLancamento::STATUS_CONFIRMADO;
                $lancamento->save();
            }

            return $deposito->refresh()->load(['equipe', 'conta']);
        });
    }

    public function rejeitar(EccFinanceiroDeposito $deposito, string $motivo, User $revisor): EccFinanceiroDeposito
    {
        abort_unless($this->visibility->userCan('ecc.financeiro.manage'), 403);
        $this->assertProvisionado($deposito);

        return DB::transaction(function () use ($deposito, $motivo, $revisor): EccFinanceiroDeposito {
            $deposito->status = EccFinanceiroDeposito::STATUS_REJEITADO;
            $deposito->motivo_rejeicao = $motivo;
            $deposito->revisado_por_user_id = $revisor->id;
            $deposito->revisado_em = now();
            $deposito->save();

            $lancamento = $deposito->lancamento;
            if ($lancamento !== null) {
                $lancamento->status = EccFinanceiroLancamento::STATUS_REJEITADO;
                $lancamento->save();
            }

            return $deposito->refresh()->load(['equipe', 'conta']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(EccFinanceiroDeposito $deposito): array
    {
        $competencia = $deposito->competencia;

        return [
            'id' => $deposito->id,
            'equipe_id' => $deposito->ecc_equipe_id,
            'equipe_nome' => $deposito->equipe?->nome,
            'competencia' => $competencia instanceof Carbon
                ? $competencia->format('Y-m')
                : Carbon::parse((string) $competencia)->format('Y-m'),
            'valor' => round((float) $deposito->valor, 2),
            'conta_id' => $deposito->ecc_financeiro_conta_id,
            'conta_nome' => $deposito->conta?->nome,
            'lancamento_id' => $deposito->ecc_financeiro_lancamento_id,
            'status' => $deposito->status,
            'motivo_rejeicao' => $deposito->motivo_rejeicao,
            'comprovante_url' => url('/api/v1/ecc/financeiro/depositos/'.$deposito->id.'/comprovante'),
            'submetido_por_user_id' => $deposito->submetido_por_user_id,
            'revisado_por_user_id' => $deposito->revisado_por_user_id,
            'revisado_em' => $deposito->revisado_em?->toIso8601String(),
            'created_at' => $deposito->created_at?->toIso8601String(),
        ];
    }

    private function igrejaId(): string
    {
        return $this->igrejaContext->current()->id;
    }

    private function assertCanList(): void
    {
        abort_unless(
            $this->visibility->userCan('ecc.financeiro.depositos.submeter')
                || $this->visibility->userCan('ecc.financeiro.view')
                || $this->visibility->userCan('ecc.financeiro.manage')
                || $this->visibility->userCan('telas.financeiro'),
            403,
        );
    }

    private function assertCanSubmeter(): void
    {
        abort_unless(
            $this->visibility->userCan('ecc.financeiro.depositos.submeter')
                || $this->visibility->userCan('ecc.financeiro.manage'),
            403,
        );
    }

    private function assertCanSubmeterOrManage(): void
    {
        $this->assertCanSubmeter();
    }

    /**
     * @return list<string>|null
     */
    private function restrictedEquipeIdsForDepositos(): ?array
    {
        if (
            $this->visibility->userCan('ecc.financeiro.manage')
            || $this->visibility->userCan('ecc.financeiro.view')
            || $this->visibility->userCan('telas.financeiro')
        ) {
            return null;
        }

        return $this->visibility->restrictedEquipeIds();
    }

    private function assertCanAccessEquipe(string $equipeId): void
    {
        if (
            $this->visibility->userCan('ecc.financeiro.manage')
            || $this->visibility->userCan('ecc.equipes.manage')
        ) {
            return;
        }

        $this->visibility->assertCanAccessEquipe($equipeId);
    }

    private function findEquipe(string $id): EccEquipe
    {
        return EccEquipe::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($id)
            ->firstOrFail();
    }

    private function parseCompetencia(string $value): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return Carbon::parse($value)->startOfMonth();
        }

        throw ValidationException::withMessages([
            'competencia' => ['Informe a competência no formato YYYY-MM.'],
        ]);
    }

    private function assertCompetenciaLivre(string $equipeId, Carbon $competencia): void
    {
        $exists = EccFinanceiroDeposito::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('ecc_equipe_id', $equipeId)
            ->whereDate('competencia', $competencia->format('Y-m-d'))
            ->whereIn('status', [
                EccFinanceiroDeposito::STATUS_PROVISIONADO,
                EccFinanceiroDeposito::STATUS_CONFIRMADO,
            ])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'competencia' => ['Já existe depósito ativo para esta equipe nesta competência.'],
            ]);
        }
    }

    private function resolveConta(?string $contaId): EccFinanceiroConta
    {
        $this->financeiro->ensureContasPadrao();

        if ($contaId !== null && $contaId !== '') {
            $conta = $this->financeiro->findConta($contaId);
            if (! $conta->ativa) {
                throw ValidationException::withMessages([
                    'conta_id' => ['A conta selecionada está inativa.'],
                ]);
            }

            return $conta;
        }

        $conta = EccFinanceiroConta::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('ativa', true)
            ->where('tipo', EccFinanceiroConta::TIPO_BANCO)
            ->orderBy('ordem')
            ->first();

        if ($conta === null) {
            throw ValidationException::withMessages([
                'conta_id' => ['Nenhuma conta bancária ativa disponível.'],
            ]);
        }

        return $conta;
    }

    private function historico(EccEquipe $equipe, Carbon $competencia): string
    {
        return sprintf(
            'Depósito mensal — %s — %s',
            $equipe->nome,
            $competencia->format('m/Y'),
        );
    }

    private function storeComprovante(UploadedFile $file): string
    {
        $path = $file->store('ecc/financeiro/comprovantes', 'public');
        if ($path === false) {
            throw ValidationException::withMessages([
                'comprovante' => ['Não foi possível salvar o comprovante.'],
            ]);
        }

        return $path;
    }

    private function deleteFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function assertProvisionado(EccFinanceiroDeposito $deposito): void
    {
        if ($deposito->status !== EccFinanceiroDeposito::STATUS_PROVISIONADO) {
            throw ValidationException::withMessages([
                'status' => ['Somente depósitos provisionados podem ser alterados nesta ação.'],
            ]);
        }
    }
}
