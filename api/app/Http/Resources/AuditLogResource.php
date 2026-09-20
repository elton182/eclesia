<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $auditableType = $this->auditable_type;
        if (is_string($auditableType) && str_contains($auditableType, '\\')) {
            $auditableType = class_basename($auditableType);
        }

        return [
            'id' => $this->ulid,
            'action' => $this->action,
            'actor_type' => $this->actor_type,
            'actor_label' => $this->actor_label,
            'actor_user_id' => $this->relationLoaded('actor')
                ? $this->actor?->ulid
                : null,
            'auditable_type' => $auditableType,
            'auditable_id' => $this->auditable_ulid,
            'auditable_label' => $this->auditable_label,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $this->metadata,
            'igreja_id' => $this->igreja_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
