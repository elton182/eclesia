<?php

declare(strict_types=1);

namespace App\Models;

use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccPerguntaEnvio extends Model
{
    use EncryptedAttribute;
    use HasUlids;

    public const STATUS_ENVIADO = 'enviado';

    public const STATUS_RESPONDIDO = 'respondido';

    protected $table = 'ecc_pergunta_envios';

    /**
     * @var list<string>
     */
    protected $encryptable = [
        'resposta_responsavel',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pergunta_id',
        'equipe_id',
        'enviado_por_user_id',
        'respostas',
        'status',
        'enviado_em',
        'resposta_responsavel',
        'respondido_em',
        'respondido_por_user_id',
        'resposta_vista_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'respostas' => 'array',
            'enviado_em' => 'datetime',
            'respondido_em' => 'datetime',
            'resposta_vista_em' => 'datetime',
        ];
    }

    public function pergunta(): BelongsTo
    {
        return $this->belongsTo(EccPergunta::class, 'pergunta_id');
    }

    public function equipe(): BelongsTo
    {
        return $this->belongsTo(EccEquipe::class, 'equipe_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_user_id');
    }

    public function respondidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondido_por_user_id');
    }
}
