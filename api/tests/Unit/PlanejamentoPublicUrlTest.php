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

    public function test_pdf_display_troca_hifen_ascii_por_nao_separavel(): void
    {
        $url = 'https://apostolus.com.br/planejamento/publico/tok?tenant=sao-jose-operario';
        $display = PlanejamentoPublicUrl::forPdfDisplay($url);

        $this->assertStringNotContainsString('sao-jose', $display);
        $this->assertStringContainsString("sao\u{2011}jose\u{2011}operario", $display);
        $this->assertSame($url, str_replace("\u{2011}", '-', $display));
    }

    public function test_blade_links_usa_href_com_hifen_ascii(): void
    {
        $url = 'https://apostolus.com.br/planejamento/publico/tok?tenant=sao-jose-operario';
        $html = view('planejamento.anual-pdf-links', [
            'anual' => (object) ['ano' => 2026, 'status' => 'coleta'],
            'igreja' => (object) ['nome' => 'Paróquia Teste'],
            'linhas' => [[
                'pastoral' => (object) ['nome' => 'Pastoral A'],
                'link' => $url,
                'qr_data_uri' => 'data:image/png;base64,AAAA',
            ]],
        ])->render();

        $this->assertStringContainsString('href="'.$url.'"', $html);
        $this->assertStringContainsString(PlanejamentoPublicUrl::forPdfDisplay($url), $html);
    }

    public function test_qr_gera_data_uri_png(): void
    {
        $uri = app(QrCodeService::class)->dataUriPng('https://app.exemplo.test/x');
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertGreaterThan(100, strlen($uri));
    }
}
