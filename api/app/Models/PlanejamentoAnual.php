<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanejamentoAnual extends Model
{
    use HasUlids;

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_COLETA = 'coleta';

    public const STATUS_REVISAO = 'revisao';

    public const STATUS_FECHADO = 'fechado';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_RASCUNHO,
        self::STATUS_COLETA,
        self::STATUS_REVISAO,
        self::STATUS_FECHADO,
    ];

    protected $table = 'planejamento_anuais';

    /** @var list<string> */
    protected $fillable = [
        'igreja_id',
        'ano',
        'status',
        'fechado_em',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'fechado_em' => 'datetime',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(PlanejamentoEvento::class, 'planejamento_anual_id');
    }

    public function isFechado(): bool
    {
        return $this->status === self::STATUS_FECHADO;
    }
}
