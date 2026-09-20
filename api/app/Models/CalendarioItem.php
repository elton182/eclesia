<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarioItem extends Model
{
    use HasUlids;

    public const SECOES = ['fds', 'semana', 'festa', 'casamento', 'obs_movel'];

    protected $table = 'calendario_itens';

    /** @var list<string> */
    protected $fillable = [
        'calendario_mensal_id',
        'local_id',
        'tipo_id',
        'data',
        'hora',
        'secao',
        'titulo',
        'pessoa_id',
        'celebrante_nome',
        'notas',
        'observacao_id',
        'ordem',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'ordem' => 'integer',
        ];
    }

    public function calendario(): BelongsTo
    {
        return $this->belongsTo(CalendarioMensal::class, 'calendario_mensal_id');
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(CalendarioLocal::class, 'local_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(CalendarioEventoTipo::class, 'tipo_id');
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function observacao(): BelongsTo
    {
        return $this->belongsTo(CalendarioObservacao::class, 'observacao_id');
    }
}
