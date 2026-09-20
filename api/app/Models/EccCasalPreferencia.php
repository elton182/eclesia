<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccCasalPreferencia extends Model
{
    use HasUlids;

    protected $table = 'ecc_casal_preferencias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'casal_id',
        'ecc_equipe_servico_id',
        'ordem',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    public function casal(): BelongsTo
    {
        return $this->belongsTo(Casal::class);
    }

    public function equipeServico(): BelongsTo
    {
        return $this->belongsTo(EccEquipeServico::class, 'ecc_equipe_servico_id');
    }
}
