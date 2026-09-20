<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EccCasalEtapa extends Model
{
    use HasUlids;

    protected $table = 'ecc_casal_etapas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'casal_id',
        'etapa',
        'ecc_numero',
        'data',
        'local',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'etapa' => 'integer',
            'data' => 'date',
        ];
    }

    public function casal(): BelongsTo
    {
        return $this->belongsTo(Casal::class);
    }
}
