<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsActivity;
use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Casal extends Model
{
    use AuditsActivity;
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
        'engajamento_paroquial',
        'habilidades',
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
        'engajamento_paroquial',
        'habilidades',
        'piloto',
        'anos_casados',
        'ecc_origem',
        'funcao_dirigente',
        'foi_coordenador_geral',
        'ficha_com_foto',
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

    /**
     * Rótulo legível para UI/auditoria (não há coluna nome no casal).
     */
    public function rotuloExibicao(): string
    {
        if (! $this->relationLoaded('pessoaA')) {
            $this->loadMissing('pessoaA');
        }
        if (! $this->relationLoaded('pessoaB')) {
            $this->loadMissing('pessoaB');
        }

        $a = is_string($this->pessoaA?->nome) ? trim($this->pessoaA->nome) : '';
        $b = is_string($this->pessoaB?->nome) ? trim($this->pessoaB->nome) : '';
        $label = trim(implode(' & ', array_filter([$a, $b], static fn (string $n): bool => $n !== '')));

        if ($label !== '') {
            return $label;
        }

        $key = $this->getAttribute('ulid') ?? $this->getKey();

        return is_scalar($key) ? (string) $key : 'Casal';
    }

    public function etapas(): HasMany
    {
        return $this->hasMany(EccCasalEtapa::class)->orderBy('etapa');
    }

    public function atividades(): HasMany
    {
        return $this->hasMany(EccCasalAtividade::class)->orderBy('ecc_numero');
    }

    public function preferencias(): HasMany
    {
        return $this->hasMany(EccCasalPreferencia::class)->orderBy('ordem');
    }
}
