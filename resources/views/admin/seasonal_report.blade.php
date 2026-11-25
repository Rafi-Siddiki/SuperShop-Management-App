@extends('pdf.layout')

@section('title', $season . ' Season Report')
@section('subtitle', $seasonStart . ' – ' . $seasonEnd)

@section('content')
    @if ($products->isEmpty())
        <p class="muted">No products were sold during the {{ strtolower($season) }} season of {{ $year }}.</p>
    @else
        <table class="summary">
            <tr>
                <td><div class="label">Products sold</div><div class="value">{{ $products->count() }}</div></td>
                <td><div class="label">Units sold</div><div class="value">{{ number_format($products->sum('total_quantity')) }}</div></td>
                <td><div class="label">Revenue</div><div class="value">{{ money($products->sum('total_revenue'), 'Tk ') }}</div></td>
            </tr>
        </table>

        <table class="data">
            <thead><tr><th style="width:7%">Rank</th><th>Product</th><th>Category</th><th class="right">Price</th><th class="right">Units sold</th><th class="right">Revenue</th></tr></thead>
            <tbody>
                @foreach ($products as $product)
                    <tr>
                        <td class="bold">{{ $loop->iteration }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category }}</td>
                        <td class="right">{{ money($product->price, 'Tk ') }}</td>
                        <td class="right">{{ number_format($product->total_quantity) }}</td>
                        <td class="right">{{ money($product->total_revenue, 'Tk ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
