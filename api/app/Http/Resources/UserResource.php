<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'is_active' => (bool) $this->is_active,
            'must_change_password' => (bool) $this->must_change_password,
            'pessoa_id' => $this->pessoa_id,
            'roles' => app(UserService::class)->rolesFor($this->resource),
            'equipes_lideradas' => app(UserService::class)->equipesLideradasFor($this->resource),
            'pastorais' => app(UserService::class)->pastoraisFor($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
