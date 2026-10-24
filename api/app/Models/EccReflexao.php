<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccReflexao extends Model
{
    use HasUlids;

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_PUBLICADO = 'publicado';

    protected $table = 'ecc_reflexoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'titulo',
        'passagem_biblica',
        'texto',
        'imagem_path',
        'publicado_em',
        'status',
        'criado_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publicado_em' => 'date',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }
}
