<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventoTipo extends Model
{
    use HasUlids;

    public const ESCOPO_ECC = 'ecc';

    public const ESCOPO_GERAL = 'geral';

    public const ESCOPO_AMBOS = 'ambos';

    protected $table = 'evento_tipos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'codigo',
        'nome',
        'abrev',
        'cor',
        'permite_compras',
        'escopo',
        'ordem',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permite_compras' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EccEvento::class, 'evento_tipo_id');
    }

    /**
     * @return list<array{codigo: string, nome: string, abrev: string, cor: string, permite_compras: bool, escopo: string}>
     */
    public static function defaultsCatalog(): array
    {
        return [
            ['codigo' => 'encontro', 'nome' => 'Encontro', 'abrev' => 'Enc', 'cor' => '#6B1C2B', 'permite_compras' => false, 'escopo' => self::ESCOPO_ECC],
            ['codigo' => 'anual', 'nome' => 'Jornada anual', 'abrev' => 'Anual', 'cor' => '#C88A5E', 'permite_compras' => true, 'escopo' => self::ESCOPO_ECC],
            ['codigo' => 'servos', 'nome' => 'Servos', 'abrev' => 'Serv', 'cor' => '#3a5f86', 'permite_compras' => false, 'escopo' => self::ESCOPO_ECC],
            ['codigo' => 'perseveranca', 'nome' => 'Perseverança', 'abrev' => 'Pers', 'cor' => '#5a7a4a', 'permite_compras' => false, 'escopo' => self::ESCOPO_ECC],
            ['codigo' => 'formacao', 'nome' => 'Formação', 'abrev' => 'Form', 'cor' => '#8a6414', 'permite_compras' => false, 'escopo' => self::ESCOPO_ECC],
            ['codigo' => 'paroquial', 'nome' => 'Evento paroquial', 'abrev' => 'Par', 'cor' => '#4E1220', 'permite_compras' => false, 'escopo' => self::ESCOPO_GERAL],
        ];
    }

    public static function slugifyCodigo(string $nome): string
    {
        $slug = Str::slug($nome, '_');

        return $slug !== '' ? mb_substr($slug, 0, 64) : 'tipo';
    }
}
