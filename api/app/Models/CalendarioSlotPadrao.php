<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarioSlotPadrao extends Model
{
    use HasUlids;

    protected $table = 'calendario_slots_padrao';

    /** @var list<string> */
    protected $fillable = [
        'igreja_id',
        'local_id',
        'dia_semana',
        'hora',
        'secao',
        'ordem',
        'ativo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(CalendarioLocal::class, 'local_id');
    }
}
