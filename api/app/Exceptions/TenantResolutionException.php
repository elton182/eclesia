<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TenantResolutionException extends Exception
{
    public static function notFound(string $identifier): self
    {
        return new self("Organização não encontrada: {$identifier}", 404);
    }

    public static function ambiguous(string $identifier): self
    {
        return new self("Nome de organização ambíguo: {$identifier}. Use o slug ou um apelido.", 422);
    }
}
