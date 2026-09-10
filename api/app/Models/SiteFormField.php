<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteFormField extends Model
{
    public const TIPOS = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox'];

    protected $table = 'site_form_fields';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_form_id',
        'nome',
        'label',
        'tipo',
        'obrigatorio',
        'opcoes',
        'ordem',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'obrigatorio' => 'boolean',
            'opcoes' => 'array',
            'ordem' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(SiteForm::class, 'site_form_id');
    }
}
