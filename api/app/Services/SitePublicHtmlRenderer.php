<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteBlock;
use App\Models\SitePage;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SitePublicHtmlRenderer
{
    private const CACHE_TTL_SECONDS = 120;

    private const DEFAULT_THEME_COLOR = '#4E1220';

    private const DEFAULT_BACKGROUND_COLOR = '#F7F4EF';

    public function __construct(
        private readonly SiteService $site,
        private readonly AppBrandingService $branding,
    ) {}

    public function renderHome(string $tenantSlug): string
    {
        $home = $this->site->publicHome();

        return $this->rememberHtml('home', function () use ($tenantSlug, $home) {
            return $this->buildDocument(
                $tenantSlug,
                null,
                $home['settings'],
                $home['page'],
            );
        });
    }

    public function renderPage(string $tenantSlug, string $pageSlug): string
    {
        $settings = $this->site->assertPublished();
        $page = $this->site->publicPage($pageSlug);

        return $this->rememberHtml($pageSlug, function () use ($tenantSlug, $pageSlug, $settings, $page) {
            return $this->buildDocument(
                $tenantSlug,
                $pageSlug,
                $settings,
                $page,
            );
        });
    }

    /**
     * Shell HTML da página /site/{slug}/instalar com manifest no primeiro paint
     * (necessário para o browser disparar beforeinstallprompt — SPEC-023).
     */
    public function renderInstallShell(string $tenantSlug): string
    {
        $settings = $this->site->assertPublished();
        $base = rtrim((string) config('app.frontend_url'), '/');
        $name = $this->brandTitle($settings);
        $manifestHref = $base.'/site/'.$tenantSlug.'/manifest.webmanifest';
        $canonical = $base.'/site/'.$tenantSlug.'/instalar';

        $branding = $this->branding->brandingPayload();
        $cores = is_array($branding['cores'] ?? null) ? $branding['cores'] : [];
        $theme = is_string($cores['primary'] ?? null) && preg_match('/^#[0-9A-Fa-f]{6}$/', $cores['primary'])
            ? $cores['primary']
            : self::DEFAULT_THEME_COLOR;

        return '<!DOCTYPE html>'."\n"
            .'<html lang="pt-BR">'."\n"
            .'<head>'."\n"
            .'  <meta charset="UTF-8">'."\n"
            .'  <meta name="viewport" content="width=device-width, initial-scale=1.0">'."\n"
            .'  <title>'.e('Instalar acesso — '.$name).'</title>'."\n"
            .'  <meta name="theme-color" content="'.e($theme).'">'."\n"
            .'  <link rel="canonical" href="'.e($canonical).'">'."\n"
            .'  <link rel="manifest" href="'.e($manifestHref).'">'."\n"
            .'  <meta name="apple-mobile-web-app-capable" content="yes">'."\n"
            .'  <meta name="mobile-web-app-capable" content="yes">'."\n"
            .'</head>'."\n"
            .'<body>'."\n"
            .'  <div id="app"></div>'."\n"
            .'</body>'."\n"
            .'</html>'."\n";
    }

    /**
     * Web App Manifest do atalho de acesso (SPEC-023 / ADR-0009).
     */
    public function renderAccessManifest(string $tenantSlug): string
    {
        $settings = $this->site->assertPublished();

        $version = (int) Cache::get(SiteService::SEO_CACHE_VERSION_KEY, 0);
        $cacheKey = 'site_access_manifest:'.$tenantSlug.':'.$version;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tenantSlug, $settings) {
            $front = rtrim((string) config('app.frontend_url'), '/');
            $name = $this->brandTitle($settings);
            $short = $this->shortAppName($name);
            $branding = $this->branding->brandingPayload();
            $cores = is_array($branding['cores'] ?? null) ? $branding['cores'] : [];
            $theme = is_string($cores['primary'] ?? null) && preg_match('/^#[0-9A-Fa-f]{6}$/', $cores['primary'])
                ? $cores['primary']
                : self::DEFAULT_THEME_COLOR;

            $icons = [
                [
                    'src' => $front.'/icons/icon-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $front.'/icons/icon-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $front.'/icons/maskable-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => $front.'/icons/maskable-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ];

            $manifest = [
                'id' => '/acesso/'.$tenantSlug,
                'name' => $name,
                'short_name' => $short,
                'description' => 'Acesso à gestão de '.$name,
                'lang' => 'pt-BR',
                'dir' => 'ltr',
                'start_url' => '/entrar?tenant='.rawurlencode($tenantSlug),
                'scope' => '/',
                'display' => 'standalone',
                'orientation' => 'any',
                'theme_color' => $theme,
                'background_color' => self::DEFAULT_BACKGROUND_COLOR,
                'categories' => ['productivity', 'business'],
                'icons' => $icons,
            ];

            return (string) json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        });
    }

    public function renderSitemap(string $tenantSlug): string
    {
        $this->site->assertPublished();

        $version = (int) Cache::get(SiteService::SEO_CACHE_VERSION_KEY, 0);
        $cacheKey = 'site_sitemap:'.$version;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tenantSlug) {
            $base = rtrim((string) config('app.frontend_url'), '/');
            $pages = SitePage::query()
                ->where('status', SitePage::STATUS_PUBLICADO)
                ->orderBy('ordem')
                ->orderBy('titulo')
                ->get(['slug', 'is_home', 'updated_at']);

            $urls = [];
            foreach ($pages as $page) {
                $loc = ($page->is_home || $page->slug === 'home')
                    ? $base.'/site/'.$tenantSlug
                    : $base.'/site/'.$tenantSlug.'/'.$page->slug;
                $lastmod = optional($page->updated_at)?->toAtomString() ?? now()->toAtomString();
                $urls[] = '  <url>'."\n"
                    .'    <loc>'.e($loc).'</loc>'."\n"
                    .'    <lastmod>'.e($lastmod).'</lastmod>'."\n"
                    .'  </url>';
            }

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
                .implode("\n", $urls)."\n"
                .'</urlset>'."\n";
        });
    }

    /**
     * @param  callable(): string  $builder
     */
    private function rememberHtml(string $pageKey, callable $builder): string
    {
        $version = (int) Cache::get(SiteService::SEO_CACHE_VERSION_KEY, 0);
        $cacheKey = 'site_html:'.$pageKey.':'.$version;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, $builder);
    }

    private function buildDocument(
        string $tenantSlug,
        ?string $pageSlug,
        SiteSetting $settings,
        ?SitePage $page,
    ): string {
        $seo = $this->resolveSeo(
            is_array($page?->seo) ? $page->seo : null,
            is_array($settings->seo) ? $settings->seo : null,
            $page?->titulo ?: $this->brandTitle($settings),
        );

        $base = rtrim((string) config('app.frontend_url'), '/');
        $canonical = $pageSlug === null || $pageSlug === '' || ($page?->is_home) || $pageSlug === 'home'
            ? $base.'/site/'.$tenantSlug
            : $base.'/site/'.$tenantSlug.'/'.$pageSlug;
        $apiBase = rtrim((string) config('app.url'), '/');
        $sitemapHref = $apiBase.'/api/v1/public/site/sitemap/'.$tenantSlug;
        $manifestHref = $base.'/site/'.$tenantSlug.'/manifest.webmanifest';

        $main = $this->renderMain($settings, $page);

        return '<!DOCTYPE html>'."\n"
            .'<html lang="pt-BR">'."\n"
            .'<head>'."\n"
            .'  <meta charset="UTF-8">'."\n"
            .'  <meta name="viewport" content="width=device-width, initial-scale=1.0">'."\n"
            .'  <title>'.e($seo['title']).'</title>'."\n"
            .'  <meta name="description" content="'.e($seo['description']).'">'."\n"
            .'  <link rel="canonical" href="'.e($canonical).'">'."\n"
            .'  <link rel="sitemap" type="application/xml" href="'.e($sitemapHref).'">'."\n"
            .'  <link rel="manifest" href="'.e($manifestHref).'">'."\n"
            .'  <meta property="og:type" content="website">'."\n"
            .'  <meta property="og:title" content="'.e($seo['title']).'">'."\n"
            .'  <meta property="og:description" content="'.e($seo['description']).'">'."\n"
            .'  <meta property="og:url" content="'.e($canonical).'">'."\n"
            .'  <meta property="og:locale" content="pt_BR">'."\n"
            .'</head>'."\n"
            .'<body>'."\n"
            .'  <div id="app">'."\n"
            .$main
            .'  </div>'."\n"
            .'</body>'."\n"
            .'</html>'."\n";
    }

    /**
     * @param  array<string, mixed>|null  $pageSeo
     * @param  array<string, mixed>|null  $settingsSeo
     * @return array{title: string, description: string}
     */
    public function resolveSeo(?array $pageSeo, ?array $settingsSeo, string $fallbackTitle): array
    {
        $s = $pageSeo ?? $settingsSeo ?? [];

        return [
            'title' => (string) ($s['title'] ?? $s['titulo'] ?? $fallbackTitle ?: 'Site'),
            'description' => (string) ($s['description'] ?? $s['descricao'] ?? ''),
        ];
    }

    private function brandTitle(SiteSetting $settings): string
    {
        $tenantName = tenant('name');
        if (is_string($tenantName) && $tenantName !== '') {
            return $tenantName;
        }

        return (string) ($settings->titulo ?: 'Site');
    }

    private function shortAppName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return 'Acesso';
        }

        return (string) Str::limit($trimmed, 12, '');
    }

    private function renderMain(SiteSetting $settings, ?SitePage $page): string
    {
        $brand = e($this->brandTitle($settings));
        $parts = ['    <main data-site-seo="1">'];
        $parts[] = '      <h1>'.$brand.'</h1>';

        if ($page === null) {
            $parts[] = '      <p>Site publicado.</p>';
            $parts[] = '    </main>';

            return implode("\n", $parts)."\n";
        }

        if ($page->titulo) {
            $parts[] = '      <h2>'.e((string) $page->titulo).'</h2>';
        }

        $blocks = $page->relationLoaded('blocks')
            ? $page->blocks
            : $page->blocks()->where('visivel', true)->orderBy('ordem')->get();

        $listCache = [
            'comunicados' => null,
            'pastorais' => null,
            'igrejas' => null,
        ];

        foreach ($blocks as $block) {
            if (! $block instanceof SiteBlock || ! $block->visivel) {
                continue;
            }
            $chunk = $this->renderBlock($block, $listCache);
            if ($chunk !== '') {
                $parts[] = $chunk;
            }
        }

        $parts[] = '    </main>';

        return implode("\n", $parts)."\n";
    }

    /**
     * @param  array{comunicados: mixed, pastorais: mixed, igrejas: mixed}  $listCache
     */
    private function renderBlock(SiteBlock $block, array &$listCache): string
    {
        $payload = is_array($block->payload) ? $block->payload : [];
        $tipo = (string) $block->tipo;

        return match ($tipo) {
            'hero' => $this->section(
                (string) ($payload['headline'] ?? ''),
                [
                    (string) ($payload['eyebrow'] ?? ''),
                    (string) ($payload['texto'] ?? ''),
                    (string) ($payload['cta_label'] ?? ''),
                    (string) ($payload['cta2_label'] ?? ''),
                ],
            ),
            'banner' => $this->section(
                (string) ($payload['titulo'] ?? $payload['headline'] ?? ''),
                [(string) ($payload['texto'] ?? $payload['subtitulo'] ?? '')],
            ),
            'richtext' => $this->section(
                '',
                [$this->plainFromHtml((string) ($payload['html'] ?? $payload['texto'] ?? ''))],
            ),
            'html' => $this->section(
                '',
                [$this->plainFromHtml((string) ($payload['html'] ?? ''))],
            ),
            'missas_horarios' => $this->section(
                (string) ($payload['titulo'] ?? 'Horários'),
                array_merge(
                    [(string) ($payload['eyebrow'] ?? ''), (string) ($payload['texto'] ?? '')],
                    $this->itemsLines($payload['items'] ?? [], ['dia', 'hora', 'local']),
                ),
            ),
            'sobre_paroquia' => $this->section(
                (string) ($payload['titulo'] ?? 'Sobre'),
                array_merge(
                    [(string) ($payload['eyebrow'] ?? ''), (string) ($payload['texto'] ?? '')],
                    $this->statsLines($payload['stats'] ?? []),
                ),
            ),
            'agenda_eventos' => $this->section(
                (string) ($payload['titulo'] ?? 'Agenda'),
                array_merge(
                    [(string) ($payload['eyebrow'] ?? '')],
                    $this->itemsLines($payload['items'] ?? [], ['titulo', 'info', 'dia', 'mes']),
                ),
            ),
            'equipe_clero' => $this->section(
                (string) ($payload['titulo'] ?? 'Equipe'),
                array_merge(
                    [(string) ($payload['eyebrow'] ?? '')],
                    $this->itemsLines($payload['items'] ?? [], ['nome', 'papel']),
                ),
            ),
            'contato_local' => $this->section(
                (string) ($payload['titulo'] ?? 'Contato'),
                [
                    (string) ($payload['eyebrow'] ?? ''),
                    (string) ($payload['endereco'] ?? ''),
                    (string) ($payload['horario_secretaria'] ?? ''),
                    (string) ($payload['telefone'] ?? ''),
                    (string) ($payload['email'] ?? ''),
                    (string) ($payload['form_titulo'] ?? ''),
                ],
            ),
            'form' => $this->section(
                (string) ($payload['titulo'] ?? $payload['nome'] ?? 'Formulário'),
                [(string) ($payload['descricao'] ?? '')],
            ),
            'comunicados_list' => $this->listSection(
                (string) ($payload['titulo'] ?? 'Comunicados'),
                (string) ($payload['eyebrow'] ?? ''),
                $listCache,
                'comunicados',
                fn () => $this->site->publicComunicados()->map(
                    static fn ($c) => trim((string) $c->titulo.' — '.Str::limit(strip_tags((string) ($c->corpo ?? $c->resumo ?? '')), 160))
                ),
            ),
            'pastorais_list' => $this->listSection(
                (string) ($payload['titulo'] ?? 'Pastorais'),
                (string) ($payload['eyebrow'] ?? ''),
                $listCache,
                'pastorais',
                fn () => $this->site->publicPastorais()->map(
                    static fn ($p) => trim((string) $p->nome.($p->descricao_publica ? ' — '.Str::limit(strip_tags((string) $p->descricao_publica), 120) : ''))
                ),
            ),
            'igrejas_list' => $this->listSection(
                (string) ($payload['titulo'] ?? 'Igrejas'),
                (string) ($payload['eyebrow'] ?? ''),
                $listCache,
                'igrejas',
                fn () => $this->site->publicIgrejas()->map(
                    static fn ($i) => trim((string) $i->nome.($i->descricao_publica ? ' — '.Str::limit(strip_tags((string) $i->descricao_publica), 120) : ''))
                ),
            ),
            default => $this->section(
                (string) ($payload['titulo'] ?? $payload['headline'] ?? ''),
                [(string) ($payload['texto'] ?? $payload['descricao'] ?? '')],
            ),
        };
    }

    /**
     * @param  list<string>  $lines
     */
    private function section(string $title, array $lines): string
    {
        $out = ['      <section>'];
        if (trim($title) !== '') {
            $out[] = '        <h3>'.e($title).'</h3>';
        }
        foreach ($lines as $line) {
            $text = trim((string) $line);
            if ($text === '') {
                continue;
            }
            $out[] = '        <p>'.e($text).'</p>';
        }
        $out[] = '      </section>';

        return count($out) > 2 ? implode("\n", $out) : '';
    }

    /**
     * @param  array{comunicados: mixed, pastorais: mixed, igrejas: mixed}  $listCache
     * @param  callable(): Collection<int, string>  $loader
     */
    private function listSection(string $title, string $eyebrow, array &$listCache, string $key, callable $loader): string
    {
        if ($listCache[$key] === null) {
            $listCache[$key] = $loader();
        }
        /** @var Collection<int, string> $items */
        $items = $listCache[$key];
        $lines = array_merge(
            [$eyebrow],
            $items->filter(static fn ($l) => trim((string) $l) !== '')->values()->all(),
        );

        return $this->section($title, $lines);
    }

    /**
     * @param  mixed  $items
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function itemsLines(mixed $items, array $fields): array
    {
        if (! is_array($items)) {
            return [];
        }
        $lines = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $parts = [];
            foreach ($fields as $field) {
                $v = trim((string) ($item[$field] ?? ''));
                if ($v !== '') {
                    $parts[] = $v;
                }
            }
            if ($parts !== []) {
                $lines[] = implode(' — ', $parts);
            }
        }

        return $lines;
    }

    /**
     * @param  mixed  $stats
     * @return list<string>
     */
    private function statsLines(mixed $stats): array
    {
        if (! is_array($stats)) {
            return [];
        }
        $lines = [];
        foreach ($stats as $stat) {
            if (! is_array($stat)) {
                continue;
            }
            $valor = trim((string) ($stat['valor'] ?? ''));
            $rotulo = trim((string) ($stat['rotulo'] ?? ''));
            if ($valor === '' && $rotulo === '') {
                continue;
            }
            $lines[] = trim($valor.' '.$rotulo);
        }

        return $lines;
    }

    private function plainFromHtml(string $html): string
    {
        $withoutDangerous = preg_replace(
            '#<(script|style)\b[^>]*>.*?</\1>#is',
            '',
            $html
        ) ?? $html;
        $text = html_entity_decode(strip_tags($withoutDangerous), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
