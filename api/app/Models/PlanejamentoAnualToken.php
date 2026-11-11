<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanejamentoAnualToken extends Model
{
    use HasUlids;

    protected $table = 'planejamento_anual_tokens';

    /** @var list<string> */
    protected $fillable = [
        'planejamento_anual_id',
        'token_hash',
        'token_preview',
        'token_encrypted',
        'expires_at',
        'revogado_em',
    ];

    /** @var list<string> */
    protected $hidden = [
        'token_hash',
        'token_encrypted',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'token_encrypted' => 'encrypted',
            'expires_at' => 'datetime',
            'revogado_em' => 'datetime',
        ];
    }

    public function planejamento(): BelongsTo
    {
        return $this->belongsTo(PlanejamentoAnual::class, 'planejamento_anual_id');
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
