<?php

declare(strict_types=1);

namespace App\Models;

use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Igreja extends Model
{
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
        'tipo',
        'endereco',
        'bairro',
        'cidade',
        'uf',
        'cep',
        'telefone',
        'email',
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
