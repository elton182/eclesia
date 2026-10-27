<?php

declare(strict_types=1);

namespace App\Services;

/**
 * URL pública do link tokenizado de uma pastoral (front SPA).
 */
class PlanejamentoPublicUrl
{
    /** Hífen não separável (U+2011) — evita soft-break no PDF que some o "-" do tenant. */
    private const NB_HYPHEN = "\u{2011}";

    public function forToken(string $plainToken): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $slug = tenant('slug');
        $qs = $slug ? '?tenant='.rawurlencode((string) $slug) : '';

        return $base.'/planejamento/publico/'.$plainToken.$qs;
    }

    /**
     * Texto visível no PDF: hífens ASCII viram U+2011 para a quebra de linha
     * não engolir o "-" (ex.: tenant=sao-jose → saojose). O href continua com ASCII.
     */
    public static function forPdfDisplay(string $url): string
    {
        return str_replace('-', self::NB_HYPHEN, $url);
    }
}
