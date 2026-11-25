@props(['accent' => 'text-brand-600'])

{{-- The store name from settings, with the last word (or a trailing "shop") in the accent colour. --}}
@php
    $name = setting('store_name');
    if (str_contains($name, ' ')) {
        $head = \Illuminate\Support\Str::beforeLast($name, ' ') . ' ';
        $tail = \Illuminate\Support\Str::afterLast($name, ' ');
    } elseif (preg_match('/^(.+?)(shop|mart|store|bazar|bazaar)$/i', $name, $m)) {
        [$head, $tail] = [$m[1], $m[2]];
    } else {
        [$head, $tail] = [$name, ''];
    }
@endphp
<span {{ $attributes }}>{{ $head }}<span class="{{ $accent }}">{{ $tail }}</span></span>
