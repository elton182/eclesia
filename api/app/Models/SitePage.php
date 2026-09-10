<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SitePage extends Model
{
    use HasUlids;

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_PUBLICADO = 'publicado';

    public const BLOCK_TYPES = [
        'hero',
        'banner',
        'richtext',
        'igrejas_list',
        'comunicados_list',
        'pastorais_list',
        'form',
        'html',
        'missas_horarios',
        'sobre_paroquia',
        'agenda_eventos',
        'equipe_clero',
        'contato_local',
    ];

    protected $table = 'site_pages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'titulo',
        'status',
        'is_home',
        'ordem',
        'mostrar_no_menu',
        'seo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_home' => 'boolean',
            'mostrar_no_menu' => 'boolean',
            'ordem' => 'integer',
            'seo' => 'array',
        ];
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(SiteBlock::class)->orderBy('ordem');
    }
}
