<?php

namespace App\Support;

use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * Barcode rules used across the shop.
 *
 * Products carry a GTIN barcode: EAN-8, UPC-A (12), EAN-13 or GTIN-14, always
 * with a valid GS1 check digit, so a mistyped code is caught before it is
 * saved. Products without a manufacturer barcode get an internal EAN-13 in
 * the GS1 "restricted circulation" range 20–29 (prefix 20 here), which never
 * clashes with real manufacturer codes:  20 + 10-digit product id + check.
 */
final class Barcode
{
    public const INTERNAL_PREFIX = '20';

    /** Normalise scanner / keyboard input: trim and drop spaces and dashes. */
    public static function normalize(?string $code): string
    {
        return preg_replace('/[\s\-]+/', '', trim((string) $code));
    }

    /** GS1 check digit for the given digits (without the check digit). */
    public static function checkDigit(string $digits): int
    {
        if ($digits === '' || ! ctype_digit($digits)) {
            throw new \InvalidArgumentException('Barcode payload must be digits.');
        }

        $sum = 0;
        $weight = 3; // the right-most payload digit has weight 3
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $sum += (int) $digits[$i] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /** True for an EAN-8, UPC-A, EAN-13 or GTIN-14 with a correct check digit. */
    public static function isValidGtin(?string $code): bool
    {
        $code = (string) $code;

        if (! ctype_digit($code) || ! in_array(strlen($code), [8, 12, 13, 14], true)) {
            return false;
        }

        return self::checkDigit(substr($code, 0, -1)) === (int) substr($code, -1);
    }

    /** Internal in-store EAN-13 for a product id. */
    public static function internalFor(int $productId): string
    {
        if ($productId < 1 || $productId > 9_999_999_999) {
            throw new \InvalidArgumentException('Product id out of range for an internal barcode.');
        }

        $payload = self::INTERNAL_PREFIX . str_pad((string) $productId, 10, '0', STR_PAD_LEFT);

        return $payload . self::checkDigit($payload);
    }

    public static function isInternal(?string $code): bool
    {
        return self::isValidGtin($code) && strlen($code) === 13 && str_starts_with($code, self::INTERNAL_PREFIX);
    }

    /** Barcode symbology that matches the code's length. */
    public static function typeFor(string $code): string
    {
        return match (strlen($code)) {
            8 => BarcodeGenerator::TYPE_EAN_8,
            12 => BarcodeGenerator::TYPE_UPC_A,
            13 => BarcodeGenerator::TYPE_EAN_13,
            default => BarcodeGenerator::TYPE_CODE_128,
        };
    }

    /** Inline SVG (for web pages). $type defaults to the symbology matching the code's length. */
    public static function svg(string $code, int $widthFactor = 2, int $height = 60, string $color = 'black', ?string $type = null): string
    {
        return (new BarcodeGeneratorSVG())->getBarcode($code, $type ?? self::typeFor($code), $widthFactor, $height, $color);
    }

    /** Code 128 SVG for free-form codes such as order numbers (never interpreted as EAN). */
    public static function code128Svg(string $code, int $widthFactor = 1, int $height = 40): string
    {
        return self::svg($code, $widthFactor, $height, 'black', BarcodeGenerator::TYPE_CODE_128);
    }

    /** PNG data URI (for PDFs; dompdf renders PNG reliably, SVG less so). */
    public static function pngDataUri(string $code, int $widthFactor = 2, int $height = 60): string
    {
        $png = (new BarcodeGeneratorPNG())->getBarcode($code, self::typeFor($code), $widthFactor, $height);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /** Human readable form, e.g. 2 000000 00012 5 → "2000000000125". */
    public static function format(string $code): string
    {
        return match (strlen($code)) {
            13 => substr($code, 0, 1) . ' ' . substr($code, 1, 6) . ' ' . substr($code, 7),
            12 => substr($code, 0, 1) . ' ' . substr($code, 1, 5) . ' ' . substr($code, 6, 5) . ' ' . substr($code, 11),
            8 => substr($code, 0, 4) . ' ' . substr($code, 4),
            default => $code,
        };
    }
}
