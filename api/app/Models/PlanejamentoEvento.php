<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlanejamentoEvento extends Model
{
    use HasUlids;

    public const STATUS_PROPOSTA = 'proposta';

    public const STATUS_CONFIRMADO = 'confirmado';

    public const STATUS_AJUSTE_SOLICITADO = 'ajuste_solicitado';

    public const STATUS_REAGENDADO = 'reagendado';

    public const STATUS_RECUSADO = 'recusado';

    public const STATUS_COTIDIANO = 'cotidiano';

    /** @var list<string> */
    public const STATUS_SOLICITACAO = [
        self::STATUS_PROPOSTA,
        self::STATUS_CONFIRMADO,
        self::STATUS_AJUSTE_SOLICITADO,
        self::STATUS_REAGENDADO,
        self::STATUS_RECUSADO,
        self::STATUS_COTIDIANO,
    ];

    protected $table = 'planejamento_eventos';

    /** @var list<string> */
    protected $fillable = [
        'planejamento_anual_id',
        'pastoral_id',
        'titulo',
        'data_inicio',
        'data_fim',
        'hora_inicio',
        'hora_fim',
        'participantes_media',
        'recorrencia_texto',
        'observacoes',
        'local_texto',
        'status_solicitacao',
        'motivo_ajuste',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'participantes_media' => 'integer',
        ];
    }

    public function planejamentoAnual(): BelongsTo
    {
        return $this->belongsTo(PlanejamentoAnual::class, 'planejamento_anual_id');
    }

    public function pastoral(): BelongsTo
    {
        return $this->belongsTo(Pastoral::class);
    }

    public function locais(): BelongsToMany
    {
        return $this->belongsToMany(
            CalendarioLocal::class,
            'planejamento_evento_local',
            'planejamento_evento_id',
            'calendario_local_id'
        );
    }
}
