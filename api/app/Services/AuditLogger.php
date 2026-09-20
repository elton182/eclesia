<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_LOGIN_SUCCESS = 'login_success';

    public const ACTION_LOGIN_FAILED = 'login_failed';

    public const ACTION_LOGOUT = 'logout';

    public const ACTION_ROLES_SYNCED = 'roles_synced';

    public const ACTOR_USER = 'user';

    public const ACTOR_SUPER_ADMIN = 'super_admin';

    public const ACTOR_ANONYMOUS = 'anonymous';

    public const ACTOR_SYSTEM = 'system';

    /** @var list<string> */
    private const ALWAYS_HIDDEN = [
        'password',
        'remember_token',
        'token',
        'secret',
    ];

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?string $auditableLabel = null,
        ?Request $request = null,
        User|SuperAdmin|null $actor = null,
    ): AuditLog {
        $request ??= request();
        $actor ??= $this->resolveActor();

        [$actorType, $actorUserId, $actorLabel] = $this->describeActor($actor);

        $igrejaId = null;
        try {
            $igrejaId = app(IgrejaContext::class)->current()->getKey();
        } catch (\Throwable) {
            $igrejaId = null;
        }

        if ($auditable !== null && $auditable->getAttribute('igreja_id')) {
            $igrejaId = $auditable->getAttribute('igreja_id');
        }

        $auditableType = $auditable !== null ? $auditable::class : null;
        $auditableKey = $auditable?->getKey();
        $auditableId = $auditableKey !== null ? (string) $auditableKey : null;
        $auditableUlid = null;
        if ($auditable !== null) {
            $ulidAttr = $auditable->getAttribute('ulid');
            $auditableUlid = is_string($ulidAttr) && $ulidAttr !== ''
                ? $ulidAttr
                : $auditableId;
        }

        return AuditLog::query()->create([
            'igreja_id' => $igrejaId,
            'actor_user_id' => $actorUserId,
            'actor_type' => $actorType,
            'actor_label' => $actorLabel,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'auditable_ulid' => $auditableUlid,
            'auditable_label' => $auditableLabel ?? $this->defaultAuditableLabel($auditable),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? $request->userAgent() : null,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public function logModelEvent(Model $model, string $action): void
    {
        $old = null;
        $new = null;

        if ($action === self::ACTION_CREATED) {
            $new = $this->sanitizeAttributes($model, $model->getAttributes());
        } elseif ($action === self::ACTION_UPDATED) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $original = [];
            foreach (array_keys($changes) as $key) {
                $original[$key] = $model->getOriginal($key);
            }
            $old = $this->sanitizeAttributes($model, $original);
            $new = $this->sanitizeAttributes($model, $changes);
        } elseif ($action === self::ACTION_DELETED) {
            $old = $this->sanitizeAttributes($model, $model->getAttributes());
        }

        $this->log(
            action: $action,
            auditable: $model,
            oldValues: $old,
            newValues: $new,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function sanitizeAttributes(Model $model, array $attributes): array
    {
        $encryptable = $this->readModelStringList($model, 'encryptable');
        $extraHidden = $this->readModelStringList($model, 'auditHidden');

        $hidden = array_unique([...self::ALWAYS_HIDDEN, ...$encryptable, ...$extraHidden]);
        $out = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            if (in_array($key, $hidden, true)) {
                if ($value === null || $value === '') {
                    $out[$key] = null;
                } else {
                    $out[$key] = '[alterado]';
                }
                continue;
            }
            if (is_array($value) || is_object($value)) {
                $out[$key] = '[complexo]';
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function readModelStringList(Model $model, string $property): array
    {
        if (! property_exists($model, $property)) {
            return [];
        }

        try {
            $ref = new \ReflectionProperty($model, $property);
            $value = $ref->getValue($model);
        } catch (\ReflectionException) {
            return [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    public function emailHint(?string $email): ?string
    {
        if ($email === null || $email === '' || ! str_contains($email, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', $email, 2);
        $prefix = mb_substr($local, 0, 2);

        return $prefix.'***@'.$domain;
    }

    private function resolveActor(): User|SuperAdmin|null
    {
        $user = Auth::guard('sanctum')->user();
        if ($user instanceof User || $user instanceof SuperAdmin) {
            return $user;
        }

        return null;
    }

/**
 * @return array{0: string, 1: int|string|null, 2: ?string}
 */
private function describeActor(User|SuperAdmin|null $actor): array
{
    if ($actor instanceof SuperAdmin) {
        return [self::ACTOR_SUPER_ADMIN, null, $actor->email ?? $actor->name ?? 'SuperAdmin'];
    }

    if ($actor instanceof User) {
        return [self::ACTOR_USER, $actor->getKey(), $actor->name ?? $actor->email];
    }

    return [self::ACTOR_ANONYMOUS, null, null];
}

    private function defaultAuditableLabel(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        foreach (['name', 'nome', 'titulo', 'email', 'slug'] as $attr) {
            $value = $model->getAttribute($attr);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $ulid = $model->getAttribute('ulid') ?? $model->getKey();

        return is_scalar($ulid) ? (string) $ulid : class_basename($model);
    }
}
