<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EccPergunta extends Model
{
    use HasUlids;

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_DISPONIVEL = 'disponivel';

    public const STATUS_ARQUIVADO = 'arquivado';

    protected $table = 'ecc_perguntas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'titulo',
        'descricao',
        'mensagem_pos_envio',
        'status',
        'responsavel_user_id',
        'disponibilizado_em',
        'criado_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'disponibilizado_em' => 'datetime',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    public function campos(): HasMany
    {
        return $this->hasMany(EccPerguntaCampo::class, 'pergunta_id')->orderBy('ordem');
    }

    public function envios(): HasMany
    {
        return $this->hasMany(EccPerguntaEnvio::class, 'pergunta_id');
    }
}
