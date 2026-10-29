<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClientDiagnostic extends Model
{
    public $timestamps = false;

    protected $table = 'client_diagnostics';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'user_id',
        'igreja_id',
        'motivo',
        'nota',
        'meta',
        'events',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'user_id',
        'igreja_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClientDiagnostic $row): void {
            if (empty($row->ulid)) {
                $row->ulid = (string) Str::ulid();
            }
            if ($row->created_at === null) {
                $row->created_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'events' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }
}
