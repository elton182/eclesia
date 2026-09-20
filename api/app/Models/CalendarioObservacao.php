<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarioObservacao extends Model
{
    use HasUlids;

    protected $table = 'calendario_observacoes';

    /** @var list<string> */
    protected $fillable = [
        'calendario_mensal_id',
        'titulo',
        'descricao',
        'ordem',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    public function calendario(): BelongsTo
    {
        return $this->belongsTo(CalendarioMensal::class, 'calendario_mensal_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CalendarioItem::class, 'observacao_id');
    }
}
