<?php

declare(strict_types=1);

namespace App\Services;

/**
 * URL pública do link tokenizado de uma pastoral (front SPA).
 */
class PlanejamentoPublicUrl
{
    public function forToken(string $plainToken): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $slug = tenant('slug');
        $qs = $slug ? '?tenant='.rawurlencode((string) $slug) : '';

        return $base.'/planejamento/publico/'.$plainToken.$qs;
    }
}
