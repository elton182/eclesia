<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarioLocal extends Model
{
    use HasUlids;

    protected $table = 'calendario_locais';

    /** @var list<string> */
    protected $fillable = [
        'igreja_id',
        'nome',
        'ordem',
        'ativo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(CalendarioSlotPadrao::class, 'local_id');
    }
}
