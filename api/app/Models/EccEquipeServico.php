<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EccEquipeServico extends Model
{
    use HasUlids;

    protected $table = 'ecc_equipes_servico';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'nome',
        'slug',
        'ordem',
        'ativo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    /**
     * @return list<array{nome: string, slug: string}>
     */
    public static function defaultsCatalog(): array
    {
        $nomes = [
            'Coordenação Geral',
            'Sala',
            'Liturgia/Vigília',
            'Círculos',
            'Café e Minimercado',
            'Cozinha',
            'Ordem e Limpeza',
            'Visitação',
            'Acolhida',
            'Secretaria',
            'Compras',
            'Palestras',
        ];

        return array_map(
            static fn (string $nome, int $i): array => [
                'nome' => $nome,
                'slug' => Str::slug($nome),
                'ordem' => $i,
            ],
            $nomes,
            array_keys($nomes),
        );
    }

    public static function seedDefaultsForIgreja(string $igrejaId): void
    {
        foreach (self::defaultsCatalog() as $row) {
            self::query()->firstOrCreate(
                [
                    'igreja_id' => $igrejaId,
                    'slug' => $row['slug'],
                ],
                [
                    'nome' => $row['nome'],
                    'ordem' => $row['ordem'],
                    'ativo' => true,
                ],
            );
        }
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function atividades(): HasMany
    {
        return $this->hasMany(EccCasalAtividade::class, 'ecc_equipe_servico_id');
    }

    public function preferencias(): HasMany
    {
        return $this->hasMany(EccCasalPreferencia::class, 'ecc_equipe_servico_id');
    }
}
