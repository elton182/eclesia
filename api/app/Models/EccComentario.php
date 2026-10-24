<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccComentario extends Model
{
    use HasUlids;

    public const ALVO_RECADO = 'recado';

    public const ALVO_REFLEXAO = 'reflexao';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_REJEITADO = 'rejeitado';

    public const STATUS_OCULTO = 'oculto';

    protected $table = 'ecc_comentarios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'alvo_tipo',
        'alvo_id',
        'user_id',
        'corpo',
        'status',
        'revisado_por_user_id',
        'revisado_em',
        'motivo_revisao',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revisado_em' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_user_id');
    }
}
