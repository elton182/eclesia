<?php

declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Gera QR Code em data-URI PNG para embutir em PDFs (DomPDF).
 */
class QrCodeService
{
    public function dataUriPng(string $payload, int $scale = 4): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => max(2, min(10, $scale)),
        ]);

        return (new QRCode($options))->render($payload);
    }
}
