<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccLeitura extends Model
{
    use HasUlids;

    public const ALVO_RECADO = 'recado';

    public const ALVO_REFLEXAO = 'reflexao';

    protected $table = 'ecc_leituras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'alvo_tipo',
        'alvo_id',
        'user_id',
        'lido_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lido_em' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
