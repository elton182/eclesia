<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteBlock extends Model
{
    use HasUlids;

    protected $table = 'site_blocks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_page_id',
        'tipo',
        'ordem',
        'visivel',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'visivel' => 'boolean',
            'payload' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SitePage::class, 'site_page_id');
    }
}
