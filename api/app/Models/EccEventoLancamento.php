<?php

declare(strict_types=1);

namespace App\Models;

use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccEventoLancamento extends Model
{
    use EncryptedAttribute;
    use HasUlids;

    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_SAIDA = 'saida';

    protected $table = 'ecc_evento_lancamentos';

    /**
     * @var list<string>
     */
    protected $encryptable = [
        'doador_nome',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ecc_evento_id',
        'tipo',
        'valor',
        'descricao',
        'casal_id',
        'ecc_equipe_id',
        'doador_nome',
        'ecc_item_compra_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EccEvento::class, 'ecc_evento_id');
    }

    public function casal(): BelongsTo
    {
        return $this->belongsTo(Casal::class, 'casal_id');
    }

    public function equipe(): BelongsTo
    {
        return $this->belongsTo(EccEquipe::class, 'ecc_equipe_id');
    }

    public function itemCompra(): BelongsTo
    {
        return $this->belongsTo(EccItemCompra::class, 'ecc_item_compra_id');
    }
}
