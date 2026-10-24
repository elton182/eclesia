<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccPerguntaCampo extends Model
{
    use HasUlids;

    public const TIPO_TEXTO_CURTO = 'texto_curto';

    public const TIPO_TEXTO_LONGO = 'texto_longo';

    public const TIPO_LISTA = 'lista';

    protected $table = 'ecc_pergunta_campos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pergunta_id',
        'ordem',
        'tipo',
        'rotulo',
        'obrigatorio',
        'opcoes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'obrigatorio' => 'boolean',
            'ordem' => 'integer',
            'opcoes' => 'array',
        ];
    }

    public function pergunta(): BelongsTo
    {
        return $this->belongsTo(EccPergunta::class, 'pergunta_id');
    }
}
