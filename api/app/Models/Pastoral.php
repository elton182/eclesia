<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pastoral extends Model
{
    use HasUlids;

    public const PAPEL_COORDENADOR = 'coordenador';

    public const PAPEL_MEMBRO = 'membro';

    /** @var list<string> */
    public const PAPEIS = [
        self::PAPEL_COORDENADOR,
        self::PAPEL_MEMBRO,
    ];

    protected $table = 'pastorais';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'nome',
        'descricao_publica',
        'contato_publico',
        'ordem',
        'publicado_no_site',
        'ativa',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'publicado_no_site' => 'boolean',
            'ativa' => 'boolean',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function membros(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pastoral_user')
            ->withPivot(['papel'])
            ->withTimestamps();
    }
}
