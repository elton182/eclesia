<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccRecado extends Model
{
    use HasUlids;

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_PUBLICADO = 'publicado';

    public const ESCOPO_PAROQUIA = 'paroquia';

    public const ESCOPO_ETAPA = 'etapa';

    public const ESCOPO_EQUIPE = 'equipe';

    protected $table = 'ecc_recados';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'titulo',
        'corpo',
        'anexo_path',
        'anexo_nome',
        'anexo_mime',
        'anexo_tamanho',
        'urgente',
        'fixado',
        'escopo',
        'equipe_id',
        'etapa',
        'publicado_em',
        'valido_ate',
        'status',
        'criado_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urgente' => 'boolean',
            'fixado' => 'boolean',
            'etapa' => 'integer',
            'publicado_em' => 'datetime',
            'valido_ate' => 'datetime',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function equipe(): BelongsTo
    {
        return $this->belongsTo(EccEquipe::class, 'equipe_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }
}
