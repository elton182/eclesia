<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CalendarioEventoTipo extends Model
{
    use HasUlids;

    public const SLUG_OUTRO = 'outro';

    public const SECAO_GRADE = 'grade';

    public const SECAO_FESTA = 'festa';

    public const SECAO_CASAMENTO = 'casamento';

    /** @var list<array{slug: string, nome: string, secao_padrao: string, exige_titulo: bool, ordem: int}> */
    public const DEFAULTS = [
        ['slug' => 'missa', 'nome' => 'Missa', 'secao_padrao' => self::SECAO_GRADE, 'exige_titulo' => false, 'ordem' => 1],
        ['slug' => 'celebracao', 'nome' => 'Celebração', 'secao_padrao' => self::SECAO_GRADE, 'exige_titulo' => false, 'ordem' => 2],
        ['slug' => 'casamento', 'nome' => 'Casamento', 'secao_padrao' => self::SECAO_CASAMENTO, 'exige_titulo' => false, 'ordem' => 3],
        ['slug' => 'batismo', 'nome' => 'Batismo', 'secao_padrao' => self::SECAO_FESTA, 'exige_titulo' => false, 'ordem' => 4],
        ['slug' => 'festa', 'nome' => 'Festa', 'secao_padrao' => self::SECAO_FESTA, 'exige_titulo' => false, 'ordem' => 5],
        ['slug' => 'outro', 'nome' => 'Outro', 'secao_padrao' => self::SECAO_FESTA, 'exige_titulo' => true, 'ordem' => 6],
    ];

    protected $table = 'calendario_evento_tipos';

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'nome',
        'secao_padrao',
        'exige_titulo',
        'ordem',
        'ativo',
        'sistema',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'exige_titulo' => 'boolean',
            'ativo' => 'boolean',
            'sistema' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CalendarioItem::class, 'tipo_id');
    }

    /** Garante os tipos padrão do catálogo (idempotente). */
    public static function seedDefaults(): void
    {
        foreach (self::DEFAULTS as $row) {
            self::query()->firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'id' => (string) Str::ulid(),
                    'nome' => $row['nome'],
                    'secao_padrao' => $row['secao_padrao'],
                    'exige_titulo' => $row['exige_titulo'],
                    'ordem' => $row['ordem'],
                    'ativo' => true,
                    'sistema' => true,
                ]
            );
        }
    }
}
