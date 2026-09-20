<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccCasalAtividade extends Model
{
    use HasUlids;

    public const STATUS = ['A', 'IC', 'C', 'N', 'NA', 'NN'];

    protected $table = 'ecc_casal_atividades';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'casal_id',
        'ecc_numero',
        'ecc_equipe_servico_id',
        'status',
        'observacao',
    ];

    public function casal(): BelongsTo
    {
        return $this->belongsTo(Casal::class);
    }

    public function equipeServico(): BelongsTo
    {
        return $this->belongsTo(EccEquipeServico::class, 'ecc_equipe_servico_id');
    }
}
