<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteForm extends Model
{
    use HasUlids;

    protected $table = 'site_forms';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'slug',
        'descricao',
        'ativo',
        'destino_email',
        'sucesso_mensagem',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(SiteFormField::class)->orderBy('ordem');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(SiteFormSubmission::class);
    }
}
