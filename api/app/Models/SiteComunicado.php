<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteComunicado extends Model
{
    use HasUlids;

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_PUBLICADO = 'publicado';

    protected $table = 'site_comunicados';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'titulo',
        'resumo',
        'corpo',
        'capa_media_id',
        'publicado_em',
        'status',
        'destaque',
        'igreja_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publicado_em' => 'datetime',
            'destaque' => 'boolean',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function capa(): BelongsTo
    {
        return $this->belongsTo(SiteMedia::class, 'capa_media_id');
    }
}
