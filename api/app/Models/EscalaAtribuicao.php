<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalaAtribuicao extends Model
{
    use HasUlids;

    protected $table = 'escala_atribuicoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'escala_ocorrencia_id',
        'escala_equipe_id',
        'pessoa_id',
        'casal_id',
    ];

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function ocorrencia(): BelongsTo
    {
        return $this->belongsTo(EscalaOcorrencia::class, 'escala_ocorrencia_id');
    }

    public function equipe(): BelongsTo
    {
        return $this->belongsTo(EscalaEquipe::class, 'escala_equipe_id');
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function casal(): BelongsTo
    {
        return $this->belongsTo(Casal::class);
    }
}
