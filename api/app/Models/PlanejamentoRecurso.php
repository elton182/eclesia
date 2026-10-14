<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlanejamentoRecurso extends Model
{
    use HasUlids;

    public const TIPO_ESPACO = 'espaco';

    public const TIPO_RECURSO = 'recurso';

    /** @var list<string> */
    public const TIPOS = [self::TIPO_ESPACO, self::TIPO_RECURSO];

    protected $table = 'planejamento_recursos';

    /** @var list<string> */
    protected $fillable = [
        'igreja_id',
        'nome',
        'tipo',
        'quantidade',
        'descricao',
        'ativo',
        'ordem',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function isEspaco(): bool
    {
        return $this->tipo === self::TIPO_ESPACO;
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function eventos(): BelongsToMany
    {
        return $this->belongsToMany(
            PlanejamentoEvento::class,
            'planejamento_evento_recurso',
            'planejamento_recurso_id',
            'planejamento_evento_id'
        )->withPivot('quantidade');
    }
}
