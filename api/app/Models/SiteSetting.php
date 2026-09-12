<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $table = 'site_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'publicado',
        'titulo',
        'subtitulo',
        'logo_path',
        'favicon_path',
        'seo',
        'contato',
        'menu',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publicado' => 'boolean',
            'seo' => 'array',
            'contato' => 'array',
            'menu' => 'array',
        ];
    }
}
