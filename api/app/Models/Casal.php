<?php

declare(strict_types=1);

namespace App\Models;

use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Casal extends Model
{
    use EncryptedAttribute;
    use HasUlids;

    protected $table = 'casais';

    /**
     * @var list<string>
     */
    protected $encryptable = [
        'endereco',
        'bairro',
        'cidade',
        'cep',
        'filhos',
        'observacoes',
        'experiencia_servico',
        'preferencia_funcao',
        'funcao_dirigente',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'ecc_equipe_id',
        'pessoa_a_id',
        'pessoa_b_id',
        'endereco',
        'bairro',
        'cidade',
        'uf',
        'cep',
        'data_casamento',
        'filhos',
        'observacoes',
        'piloto',
        'anos_casados',
        'ecc_origem',
        'experiencia_servico',
        'preferencia_funcao',
        'funcao_dirigente',
        'foi_coordenador_geral',
        'ficha_com_foto',
        'etapa_2',
        'etapa_3',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_casamento' => 'date',
            'piloto' => 'boolean',
            'foi_coordenador_geral' => 'boolean',
            'ficha_com_foto' => 'boolean',
            'anos_casados' => 'integer',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function equipe(): BelongsTo
    {
        return $this->belongsTo(EccEquipe::class, 'ecc_equipe_id');
    }

    public function pessoaA(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_a_id');
    }

    public function pessoaB(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_b_id');
    }
}
