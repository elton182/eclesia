<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarioIndisponibilidade extends Model
{
    use HasUlids;

    protected $table = 'calendario_indisponibilidades';

    /** @var list<string> */
    protected $fillable = [
        'calendario_mensal_id',
        'coleta_link_id',
        'user_id',
        'pessoa_id',
        'nome_exibicao',
        'data',
        'motivo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function calendario(): BelongsTo
    {
        return $this->belongsTo(CalendarioMensal::class, 'calendario_mensal_id');
    }
}
