<?php

use App\Support\Barcode;
use App\Support\Money;

test('check digits match known real-world barcodes', function (string $code) {
    expect(Barcode::isValidGtin($code))->toBeTrue()
        ->and(Barcode::checkDigit(substr($code, 0, -1)))->toBe((int) substr($code, -1));
})->with([
    'EAN-13' => '4006381333931',
    'EAN-13 (GS1 example)' => '5901234123457',
    'ISBN-13' => '9780141439518',
    'UPC-A' => '036000291452',
    'EAN-8' => '96385074',
    'GTIN-14' => '10012345678902',
]);

test('wrong check digits, wrong lengths and non-digits are rejected', function (?string $code) {
    expect(Barcode::isValidGtin($code))->toBeFalse();
})->with(['4006381333932', '5901234123450', '123', '12345678901', 'ABCDEFGHIJKLM', '', null, '40063813339 31']);

test('internal barcodes are valid EAN-13 in the 20 prefix range and unique per id', function () {
    $codes = array_map(fn ($id) => Barcode::internalFor($id), [1, 2, 10, 999, 1234567]);

    foreach ($codes as $code) {
        expect($code)->toHaveLength(13)->toStartWith('20')
            ->and(Barcode::isValidGtin($code))->toBeTrue()
            ->and(Barcode::isInternal($code))->toBeTrue();
    }
    expect(array_unique($codes))->toHaveCount(5)
        ->and(Barcode::internalFor(1))->toBe('2000000000015')
        ->and(Barcode::isInternal('5901234123457'))->toBeFalse();
});

test('scanner input is normalised', function () {
    expect(Barcode::normalize(" 590-1234 123457\n"))->toBe('5901234123457');
});

test('barcodes render as svg and png', function () {
    expect(Barcode::svg('5901234123457'))->toContain('<svg')
        ->and(Barcode::pngDataUri('2000000000015'))->toStartWith('data:image/png;base64,')
        ->and(Barcode::code128Svg('SS-000123'))->toContain('<svg');
});

test('money maths is exact in paisa', function () {
    expect(Money::toPaisa('0.1') + Money::toPaisa('0.2'))->toBe(30)
        ->and(Money::fromPaisa(123456))->toBe('1234.56')
        ->and(Money::fromPaisa(5))->toBe('0.05')
        ->and(Money::fromPaisa(-250))->toBe('-2.50')
        ->and(Money::percentOf(99999, 15))->toBe(15000)   // 149.9985 → 150.00
        ->and(Money::toPaisa(null))->toBe(0);
});
