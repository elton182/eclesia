<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanejamentoPastoralToken extends Model
{
    use HasUlids;

    protected $table = 'planejamento_pastoral_tokens';

    /** @var list<string> */
    protected $fillable = [
        'planejamento_anual_id',
        'pastoral_id',
        'token',
        'token_preview',
        'expires_at',
        'revogado_em',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revogado_em' => 'datetime',
        ];
    }

    public function planejamento(): BelongsTo
    {
        return $this->belongsTo(PlanejamentoAnual::class, 'planejamento_anual_id');
    }

    public function pastoral(): BelongsTo
    {
        return $this->belongsTo(Pastoral::class);
    }

    public function isAtivo(): bool
    {
        if ($this->revogado_em !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
