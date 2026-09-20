<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarioMensal extends Model
{
    use HasUlids;

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_COLETA = 'coleta';

    public const STATUS_MONTAGEM = 'montagem';

    public const STATUS_FECHADO = 'fechado';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_RASCUNHO,
        self::STATUS_COLETA,
        self::STATUS_MONTAGEM,
        self::STATUS_FECHADO,
    ];

    protected $table = 'calendario_mensais';

    /** @var list<string> */
    protected $fillable = [
        'igreja_id',
        'ano',
        'mes',
        'status',
        'titulo',
        'subtitulo',
        'fechado_em',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'mes' => 'integer',
            'fechado_em' => 'datetime',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CalendarioItem::class, 'calendario_mensal_id');
    }

    public function observacoes(): HasMany
    {
        return $this->hasMany(CalendarioObservacao::class, 'calendario_mensal_id')
            ->orderBy('ordem')
            ->orderBy('created_at');
    }

    public function temposLiturgicos(): HasMany
    {
        return $this->hasMany(CalendarioTempoLiturgico::class, 'calendario_mensal_id')
            ->orderBy('data_domingo');
    }

    public function coletaLinks(): HasMany
    {
        return $this->hasMany(CalendarioColetaLink::class, 'calendario_mensal_id');
    }

    public function indisponibilidades(): HasMany
    {
        return $this->hasMany(CalendarioIndisponibilidade::class, 'calendario_mensal_id');
    }

    public function isFechado(): bool
    {
        return $this->status === self::STATUS_FECHADO;
    }
}
