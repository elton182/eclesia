<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanejamentoEvento extends Model
{
    use HasUlids;

    public const STATUS_PROPOSTA = 'proposta';

    public const STATUS_EM_REVISAO = 'em_revisao';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_REJEITADO = 'rejeitado';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PROPOSTA,
        self::STATUS_EM_REVISAO,
        self::STATUS_APROVADO,
        self::STATUS_REJEITADO,
    ];

    public const VIA_LOGIN = 'login';

    public const VIA_LINK = 'link';

    protected $table = 'planejamento_eventos';

    /** @var list<string> */
    protected $fillable = [
        'planejamento_anual_id',
        'pastoral_id',
        'titulo',
        'descricao',
        'data_inicio',
        'data_fim',
        'dia_inteiro',
        'status',
        'criado_via',
        'notas_revisao',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
            'dia_inteiro' => 'boolean',
        ];
    }

    public function planejamento(): BelongsTo
    {
        return $this->belongsTo(PlanejamentoAnual::class, 'planejamento_anual_id');
    }

    public function pastoral(): BelongsTo
    {
        return $this->belongsTo(Pastoral::class);
    }

    public function isProposta(): bool
    {
        return $this->status === self::STATUS_PROPOSTA;
    }
}
