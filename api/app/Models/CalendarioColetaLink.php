<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarioColetaLink extends Model
{
    use HasUlids;

    protected $table = 'calendario_coleta_links';

    /** @var list<string> */
    protected $fillable = [
        'calendario_mensal_id',
        'token',
        'token_hash',
        'token_preview',
        'rotulo',
        'expira_em',
        'ativo',
    ];


    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
            'ativo' => 'boolean',
        ];
    }

    public function calendario(): BelongsTo
    {
        return $this->belongsTo(CalendarioMensal::class, 'calendario_mensal_id');
    }

    public function indisponibilidades(): HasMany
    {
        return $this->hasMany(CalendarioIndisponibilidade::class, 'coleta_link_id');
    }
}
