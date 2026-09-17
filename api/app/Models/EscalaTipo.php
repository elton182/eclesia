<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalaTipo extends Model
{
    use HasUlids;

    protected $table = 'escala_tipos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'nome',
        'descricao',
        'unidade_preferida',
        'recorrencia',
    ];

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function equipes(): HasMany
    {
        return $this->hasMany(EscalaEquipe::class, 'escala_tipo_id');
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(EscalaOcorrencia::class, 'escala_tipo_id');
    }
}
