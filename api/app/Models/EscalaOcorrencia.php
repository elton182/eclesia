<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalaOcorrencia extends Model
{
    use HasUlids;

    protected $table = 'escala_ocorrencias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'escala_tipo_id',
        'titulo',
        'inicia_em',
        'termina_em',
        'local',
        'evento_agenda_id',
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

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(EscalaTipo::class, 'escala_tipo_id');
    }

    public function eventoAgenda(): BelongsTo
    {
        return $this->belongsTo(EventoAgenda::class, 'evento_agenda_id');
    }

    public function atribuicoes(): HasMany
    {
        return $this->hasMany(EscalaAtribuicao::class, 'escala_ocorrencia_id');
    }
}
