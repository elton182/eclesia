<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PlanejamentoPublicUrl;
use App\Services\QrCodeService;
use Tests\TestCase;

class PlanejamentoPublicUrlTest extends TestCase
{
    public function test_monta_url_publica_com_frontend_e_tenant(): void
    {
        config(['app.frontend_url' => 'https://app.exemplo.test']);

        // Sem tenant inicializado → sem query
        $url = app(PlanejamentoPublicUrl::class)->forToken('abc123');
        $this->assertSame('https://app.exemplo.test/planejamento/publico/abc123', $url);
    }

    public function test_qr_gera_data_uri_png(): void
    {
        $uri = app(QrCodeService::class)->dataUriPng('https://app.exemplo.test/x');
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertGreaterThan(100, strlen($uri));
    }
}
