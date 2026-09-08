<?php

declare(strict_types=1);

namespace App\Models;

use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, EncryptedAttribute;

    /**
     * @var list<string>
     */
    protected $encryptable = [
        'name',
        'email',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'name',
        'email',
        'password',
        'is_active',
        'pessoa_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->ulid)) {
                $user->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Busca por e-mail criptografado (MySQL) ou decrypt em memória (SQLite/testes).
     */
    public static function findByEmail(string $email): ?self
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return static::query()->get()->first(fn (self $user) => $user->email === $email);
        }

        return static::whereEncrypted('email', $email)->first();
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return static::query()->get()->contains(
                fn (self $user) => $user->email === $email && ($exceptId === null || $user->id !== $exceptId)
            );
        }

        $query = static::whereEncrypted('email', $email);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }
}
