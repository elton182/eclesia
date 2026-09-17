<?php

declare(strict_types=1);

namespace App\Models;

use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

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
        'foto_path',
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

    public function fotoUrl(): ?string
    {
        if ($this->foto_path === null || $this->foto_path === '') {
            return null;
        }

        if (str_starts_with($this->foto_path, 'http://') || str_starts_with($this->foto_path, 'https://')) {
            return $this->foto_path;
        }

        $tenantSlug = tenant('slug');
        if (! is_string($tenantSlug) || $tenantSlug === '') {
            return null;
        }

        // URL assinada: <img> não envia X-Tenant/Bearer; o middleware aceita ?tenant=
        // e o arquivo fica no storage do tenant (não no symlink public/storage central).
        return URL::temporarySignedRoute(
            'pessoas.foto.show',
            now()->addHours(12),
            [
                'id' => $this->id,
                'tenant' => $tenantSlug,
            ],
        );
    }
}
