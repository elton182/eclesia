<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarioTempoLiturgico extends Model
{
    use HasUlids;

    protected $table = 'calendario_tempos_liturgicos';

    /** @var list<string> */
    protected $fillable = [
        'calendario_mensal_id',
        'data_domingo',
        'rotulo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'data_domingo' => 'date',
        ];
    }

    public function calendario(): BelongsTo
    {
        return $this->belongsTo(CalendarioMensal::class, 'calendario_mensal_id');
    }
}
