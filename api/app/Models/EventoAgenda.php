<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoAgenda extends Model
{
    use HasUlids;

    protected $table = 'evento_agenda';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'titulo',
        'inicia_em',
        'termina_em',
        'local',
        'dono_modulo',
        'tipo',
        'referencia_tipo',
        'referencia_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inicia_em' => 'datetime',
            'termina_em' => 'datetime',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }
}
