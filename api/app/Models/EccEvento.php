<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EccEvento extends Model
{
    use HasUlids;

    public const ORIGEM_ECC = 'ecc';

    public const ORIGEM_GERAL = 'geral';

    /** @deprecated Use EventoTipo cadastrável */
    public const TIPOS = [
        'encontro',
        'anual',
        'servos',
        'perseveranca',
        'formacao',
    ];

    protected $table = 'ecc_eventos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'igreja_id',
        'titulo',
        'tipo',
        'evento_tipo_id',
        'origem',
        'inicia_em',
        'termina_em',
        'local',
        'casal_compras_id',
        'evento_agenda_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inicia_em' => 'datetime',
            'termina_em' => 'datetime',
        ];
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    public function eventoTipo(): BelongsTo
    {
        return $this->belongsTo(EventoTipo::class, 'evento_tipo_id');
    }

    public function casalCompras(): BelongsTo
    {
        return $this->belongsTo(Casal::class, 'casal_compras_id');
    }

    public function eventoAgenda(): BelongsTo
    {
        return $this->belongsTo(EventoAgenda::class, 'evento_agenda_id');
    }

    public function casais(): BelongsToMany
    {
        return $this->belongsToMany(Casal::class, 'ecc_evento_casal', 'ecc_evento_id', 'casal_id')
            ->withPivot('convidados')
            ->withTimestamps();
    }

    public function itensCompra(): HasMany
    {
        return $this->hasMany(EccItemCompra::class, 'ecc_evento_id');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(EccEventoLancamento::class, 'ecc_evento_id');
    }

    public function permiteCompras(): bool
    {
        // Eventos do módulo ECC sempre têm lista de compras + caixa.
        if ($this->origem === self::ORIGEM_ECC) {
            return true;
        }

        if ($this->relationLoaded('eventoTipo') && $this->eventoTipo) {
            return (bool) $this->eventoTipo->permite_compras;
        }

        return $this->tipo === 'anual';
    }

    public function isAnual(): bool
    {
        return $this->permiteCompras();
    }
}
