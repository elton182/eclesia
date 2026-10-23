<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsActivity;
use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use AuditsActivity, HasApiTokens, HasFactory, HasRoles, Notifiable, EncryptedAttribute;

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
        'username',
        'password',
        'must_change_password',
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
     * Resolve login por e-mail (LGPD) ou username (SPEC-021).
     */
    public static function findByLogin(string $login): ?self
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        if (str_contains($login, '@')) {
            return static::findByEmail($login);
        }

        return static::query()->where('username', $login)->first();
    }

    public static function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $query = static::query()->where('username', $username);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * Normaliza login digitado para username (slug), SPEC-021 / SPEC-026.
     * Alinhado ao front (`normalizeEquipeUsername`): sem transliterar `@` → `at`.
     */
    public static function normalizeUsername(string $value): string
    {
        $ascii = Str::lower(Str::ascii(trim($value)));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? '';

        return trim($slug, '-');
    }

    public static function isSyntheticEmail(?string $email): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        $lower = Str::lower($email);

        return str_ends_with($lower, '@login.local')
            || str_ends_with($lower, '@equipe.local');
    }

    /**
     * E-mail interno quando o usuário não tem e-mail real (SPEC-026).
     */
    public static function syntheticLoginEmail(string $username): string
    {
        return $username.'@login.local';
    }

    /**
     * E-mail exposto na API: null para sintéticos.
     */
    public function publicEmail(): ?string
    {
        return static::isSyntheticEmail($this->email) ? null : $this->email;
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
            'must_change_password' => 'boolean',
        ];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function equipesLideradas(): BelongsToMany
    {
        return $this->belongsToMany(
            EccEquipe::class,
            'ecc_equipe_user',
            'user_id',
            'ecc_equipe_id'
        );
    }

    public function pastorais(): BelongsToMany
    {
        return $this->belongsToMany(Pastoral::class, 'pastoral_user')
            ->withPivot(['papel'])
            ->withTimestamps();
    }
}
