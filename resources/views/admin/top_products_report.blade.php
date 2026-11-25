@extends('pdf.layout')

@section('title', 'Top Products')
@section('subtitle', 'All-time best sellers, cancelled orders excluded')

@section('content')
    <table class="summary">
        <tr>
            <td><div class="label">Products sold</div><div class="value">{{ $topProducts->count() }}</div></td>
            <td><div class="label">Units sold</div><div class="value">{{ number_format($topProducts->sum('total_quantity')) }}</div></td>
            <td><div class="label">Revenue</div><div class="value">{{ money($topProducts->sum('total_revenue'), 'Tk ') }}</div></td>
        </tr>
    </table>

    @if ($topProducts->isEmpty())
        <p class="muted">No products have been sold yet.</p>
    @else
        <table class="data">
            <thead><tr><th style="width:7%">Rank</th><th>Product</th><th>Category</th><th class="right">Units sold</th><th class="right">Revenue</th></tr></thead>
            <tbody>
                @foreach ($topProducts as $product)
                    <tr>
                        <td class="bold">{{ $loop->iteration }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category }}</td>
                        <td class="right">{{ number_format($product->total_quantity) }}</td>
                        <td class="right">{{ money($product->total_revenue, 'Tk ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
