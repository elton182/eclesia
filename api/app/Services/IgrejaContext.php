<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Igreja;
use RuntimeException;

class IgrejaContext
{
    private ?Igreja $igreja = null;

    public function current(): Igreja
    {
        if ($this->igreja !== null) {
            return $this->igreja;
        }

        $igreja = Igreja::query()->orderBy('created_at')->first();

        if ($igreja === null) {
            throw new RuntimeException('Nenhuma igreja cadastrada neste tenant.');
        }

        $this->igreja = $igreja;

        return $igreja;
    }

    public function set(?Igreja $igreja): void
    {
        $this->igreja = $igreja;
    }
}
