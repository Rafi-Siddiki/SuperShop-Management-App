<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* A4 sticker sheet, 3 columns × 8 rows of 63.5 × 33.9 mm (Avery L7159 layout):
           top margin 13.1 mm, left margin 6.4 mm, column pitch 66 mm, row pitch 33.9 mm. */
        @page { size: A4; margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; }
        .page { position: relative; width: 210mm; height: 296mm; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .label { position: absolute; width: 63.5mm; height: 33.9mm; overflow: hidden; text-align: center; }
        .inner { padding: 2.2mm 3mm 0; }
        .name { font-size: 7.5pt; font-weight: bold; height: 6.6mm; overflow: hidden; line-height: 3.3mm; }
        .price { font-size: 11pt; font-weight: bold; margin-top: 0.4mm; }
        .bars { width: 46mm; height: 11mm; margin-top: 0.8mm; }
        .code { font-size: 7pt; letter-spacing: 0.6pt; }
    </style>
</head>
<body>
@foreach ($pages as $cells)
    <div class="page">
        @foreach ($cells as $i => $product)
            @continue($product === null)
            @php
                $col = $i % 3;
                $row = intdiv($i, 3);
            @endphp
            <div class="label" style="left: {{ 6.4 + $col * 66 }}mm; top: {{ 13.1 + $row * 33.9 }}mm;">
                <div class="inner">
                    <div class="name">{{ \Illuminate\Support\Str::limit($product->name, 60) }}</div>
                    @if ($showPrice)
                        <div class="price">Tk {{ number_format($product->price, (float) $product->price == floor((float) $product->price) ? 0 : 2) }}</div>
                    @endif
                    <img class="bars" src="{{ $images[$product->id] }}" alt="">
                    <div class="code">{{ \App\Support\Barcode::format($product->barcode) }}</div>
                </div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
