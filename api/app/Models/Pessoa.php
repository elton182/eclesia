<?php

declare(strict_types=1);

namespace App\Models;

use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pessoa extends Model
{
    use EncryptedAttribute;
    use HasUlids;

    protected $table = 'pessoas';

    /**
     * @var list<string>
     */
    protected $encryptable = [
        'nome',
        'email',
        'telefone',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'nome',
        'email',
        'telefone',
        'data_nascimento',
        'sexo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }
}
