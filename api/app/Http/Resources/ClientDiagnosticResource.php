<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ClientDiagnostic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClientDiagnostic
 */
class ClientDiagnosticResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'id' => $this->ulid,
            'motivo' => $this->motivo,
            'nota' => $this->nota,
            'meta' => $this->meta,
            'events' => $this->events,
            'events_count' => is_array($this->events) ? count($this->events) : 0,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'user' => $user ? [
                'id' => $user->ulid,
                'name' => $user->name,
                'username' => $user->username,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
