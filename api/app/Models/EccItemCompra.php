<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccItemCompra extends Model
{
    use HasUlids;

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_DOADO = 'doado';

    public const STATUS_COMPRADO = 'comprado';

    protected $table = 'ecc_itens_compra';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ecc_evento_id',
        'nome',
        'qtd',
        'unidade',
        'status',
        'doador_casal_id',
        'valor_gasto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qtd' => 'decimal:2',
            'valor_gasto' => 'decimal:2',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EccEvento::class, 'ecc_evento_id');
    }

    public function doador(): BelongsTo
    {
        return $this->belongsTo(Casal::class, 'doador_casal_id');
    }
}
