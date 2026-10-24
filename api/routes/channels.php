<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('eclesia.user.{ulid}', function ($user, string $ulid): bool {
    if (! $user instanceof User) {
        return false;
    }

    return (string) $user->ulid === (string) $ulid;
});
