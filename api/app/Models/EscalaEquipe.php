<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalaEquipe extends Model
{
    use HasUlids;

    protected $table = 'escala_equipes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'escala_tipo_id',
        'nome',
        'cor',
        'ordem',
        'vagas_sugeridas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'vagas_sugeridas' => 'integer',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(EscalaTipo::class, 'escala_tipo_id');
    }

    public function atribuicoes(): HasMany
    {
        return $this->hasMany(EscalaAtribuicao::class, 'escala_equipe_id');
    }
}
