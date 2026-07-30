<?php

namespace App\Services;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeImageService
{
    public function png(string $data, int $size = 900): string
    {
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 30,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(23, 74, 52),
            backgroundColor: new Color(255, 255, 255),
        );

        return (new PngWriter)->write($qrCode)->getString();
    }

    public function dataUri(string $data, int $size = 900): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($data, $size));
    }
}
