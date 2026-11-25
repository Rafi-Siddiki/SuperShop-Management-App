@extends('pdf.layout')

@section('title', 'Stock Report')
@section('subtitle', 'As of ' . $currentDateTime)

@section('content')
    @php
        $units = $products->sum(fn ($p) => $p->stock_quantity);
        $value = $products->sum(fn ($p) => $p->stock_quantity * $p->price);
        $low = $products->filter->is_low_stock->count();
        $out = $products->filter->is_out_of_stock->count();
    @endphp

    <table class="summary">
        <tr>
            <td><div class="label">Products</div><div class="value">{{ $products->count() }}</div></td>
            <td><div class="label">Units in stock</div><div class="value">{{ number_format($units) }}</div></td>
            <td><div class="label">Retail value</div><div class="value">{{ money($value, 'Tk ') }}</div></td>
            <td><div class="label">Low / out</div><div class="value">{{ $low }} / {{ $out }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead><tr><th>Product</th><th>Category</th><th class="right">Price</th><th class="right">In stock</th><th class="right">Stock value</th></tr></thead>
        <tbody>
            @foreach ($products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category }}</td>
                    <td class="right">{{ money($product->price, 'Tk ') }}</td>
                    <td class="right">
                        @if ($product->is_out_of_stock)
                            <span class="pill pill-red">OUT</span>
                        @elseif ($product->is_low_stock)
                            <span class="pill pill-amber">{{ $product->stock_quantity }} LOW</span>
                        @else
                            {{ number_format($product->stock_quantity) }}
                        @endif
                    </td>
                    <td class="right">{{ money($product->stock_quantity * $product->price, 'Tk ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
