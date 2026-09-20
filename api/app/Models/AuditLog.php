<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'igreja_id',
        'actor_user_id',
        'actor_type',
        'actor_label',
        'action',
        'auditable_type',
        'auditable_id',
        'auditable_ulid',
        'auditable_label',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'actor_user_id',
        'auditable_id',
        'igreja_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log): void {
            if (empty($log->ulid)) {
                $log->ulid = (string) Str::ulid();
            }
            if ($log->created_at === null) {
                $log->created_at = now();
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
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }
}
