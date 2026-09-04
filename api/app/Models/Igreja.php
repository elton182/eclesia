<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Igreja extends Model
{
    use HasUlids;

    protected $table = 'igrejas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
    ];

    public function pessoas(): HasMany
    {
        return $this->hasMany(Pessoa::class);
    }

    public function equipes(): HasMany
    {
        return $this->hasMany(EccEquipe::class);
    }

    public function casais(): HasMany
    {
        return $this->hasMany(Casal::class);
    }
}
