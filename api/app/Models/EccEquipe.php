<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EccEquipe extends Model
{
    use HasUlids;

    protected $table = 'ecc_equipes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'nome',
        'cor',
    ];

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function casais(): HasMany
    {
        return $this->hasMany(Casal::class, 'ecc_equipe_id');
    }
}
