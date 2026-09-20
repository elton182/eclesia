<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsActivity;
use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Igreja extends Model
{
    use AuditsActivity;
    use EncryptedAttribute;
    use HasUlids;

    public const TIPOS = ['paroquia', 'comunidade', 'outro'];

    protected $table = 'igrejas';

    /**
     * @var list<string>
     */
    protected $encryptable = [
        'endereco',
        'bairro',
        'cidade',
        'cep',
        'telefone',
        'email',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'slug',
        'tipo',
        'endereco',
        'bairro',
        'cidade',
        'uf',
        'diocese',
        'cep',
        'telefone',
        'email',
        'publicado_no_site',
        'descricao_publica',
        'horario_missas',
        'banner_media_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publicado_no_site' => 'boolean',
        ];
    }

    public function pessoas(): HasMany
    {
        return $this->hasMany(Pessoa::class);
    }

    public function equipes(): HasMany
    {
        return $this->hasMany(EccEquipe::class);
    }

    public function equipesServico(): HasMany
    {
        return $this->hasMany(EccEquipeServico::class);
    }

    public function casais(): HasMany
    {
        return $this->hasMany(Casal::class);
    }

    public function pastorais(): HasMany
    {
        return $this->hasMany(Pastoral::class);
    }
}
