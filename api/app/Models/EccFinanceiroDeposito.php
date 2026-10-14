<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccFinanceiroDeposito extends Model
{
    use HasUlids;

    public const STATUS_PROVISIONADO = 'provisionado';

    public const STATUS_CONFIRMADO = 'confirmado';

    public const STATUS_REJEITADO = 'rejeitado';

    protected $table = 'ecc_financeiro_depositos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'ecc_equipe_id',
        'competencia',
        'valor',
        'ecc_financeiro_conta_id',
        'ecc_financeiro_lancamento_id',
        'comprovante_path',
        'status',
        'motivo_rejeicao',
        'submetido_por_user_id',
        'revisado_por_user_id',
        'revisado_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'competencia' => 'date',
            'valor' => 'decimal:2',
            'revisado_em' => 'datetime',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class, 'igreja_id');
    }

    public function equipe(): BelongsTo
    {
        return $this->belongsTo(EccEquipe::class, 'ecc_equipe_id');
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(EccFinanceiroConta::class, 'ecc_financeiro_conta_id');
    }

    public function lancamento(): BelongsTo
    {
        return $this->belongsTo(EccFinanceiroLancamento::class, 'ecc_financeiro_lancamento_id');
    }

    public function submetidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submetido_por_user_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_user_id');
    }
}
