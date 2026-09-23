<?php

declare(strict_types=1);

namespace App\Models;

use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccFinanceiroLancamento extends Model
{
    use EncryptedAttribute;
    use HasUlids;

    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_SAIDA = 'saida';

    protected $table = 'ecc_financeiro_lancamentos';

    /**
     * @var list<string>
     */
    protected $encryptable = [
        'historico',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'ecc_financeiro_conta_id',
        'data',
        'historico',
        'tipo',
        'valor',
        'transferencia_id',
        'abertura',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
            'abertura' => 'boolean',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class, 'igreja_id');
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(EccFinanceiroConta::class, 'ecc_financeiro_conta_id');
    }
}
