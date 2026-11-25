@extends('pdf.layout')

@section('title', 'Monthly Sales Report')
@section('subtitle', $month . ' ' . $year)

@section('content')
    @if ($salesData->isEmpty())
        <p class="muted">No sales were recorded in {{ $month }} {{ $year }}.</p>
    @else
        <table class="summary">
            <tr>
                <td><div class="label">Products sold</div><div class="value">{{ $salesData->count() }}</div></td>
                <td><div class="label">Units sold</div><div class="value">{{ number_format($salesData->sum('total_quantity')) }}</div></td>
                <td><div class="label">Revenue</div><div class="value">{{ money($salesData->sum('total_revenue'), 'Tk ') }}</div></td>
            </tr>
        </table>

        <table class="data">
            <thead><tr><th style="width:7%">#</th><th>Product</th><th>Category</th><th class="right">Units sold</th><th class="right">Revenue</th></tr></thead>
            <tbody>
                @foreach ($salesData as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->category }}</td>
                        <td class="right">{{ number_format($row->total_quantity) }}</td>
                        <td class="right">{{ money($row->total_revenue, 'Tk ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
