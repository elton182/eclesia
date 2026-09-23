<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EccFinanceiroConta extends Model
{
    use HasUlids;

    public const TIPO_BANCO = 'banco';

    public const TIPO_ESPECIE = 'especie';

    protected $table = 'ecc_financeiro_contas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'nome',
        'tipo',
        'ordem',
        'ativa',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'ativa' => 'boolean',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class, 'igreja_id');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(EccFinanceiroLancamento::class, 'ecc_financeiro_conta_id');
    }
}
