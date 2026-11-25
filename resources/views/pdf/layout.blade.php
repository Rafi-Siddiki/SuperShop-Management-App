<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title') · {{ setting('store_name') }}</title>
    <style>
        @page { margin: 32px 36px 56px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; }
        .brand-bar { height: 6px; background: #e51d1d; margin-bottom: 18px; }
        .header { width: 100%; margin-bottom: 22px; }
        .header td { vertical-align: top; }
        .logo { font-size: 22px; font-weight: bold; color: #0f172a; }
        .logo span { color: #e51d1d; }
        .muted { color: #64748b; }
        .small { font-size: 9.5px; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #0f172a; }
        h2 { font-size: 13px; margin: 18px 0 8px; color: #0f172a; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #0f172a; color: #fff; text-align: left; padding: 7px 8px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .4px; }
        table.data td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .pill { display: inline-block; padding: 2px 7px; border-radius: 9px; font-size: 9px; font-weight: bold; }
        .pill-red { background: #ffe1e1; color: #a01414; }
        .pill-amber { background: #fef3c7; color: #92400e; }
        .pill-green { background: #d1fae5; color: #065f46; }
        .summary { width: 100%; margin: 6px 0 16px; border-collapse: separate; border-spacing: 6px 0; }
        .summary td { background: #f1f5f9; padding: 9px 11px; border-radius: 6px; }
        .summary .label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
        .summary .value { font-size: 15px; font-weight: bold; color: #0f172a; margin-top: 2px; }
        .footer { position: fixed; bottom: -36px; left: 0; right: 0; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        @yield('styles')
    </style>
</head>
<body>
<div class="footer">
    <table width="100%"><tr>
        <td>{{ setting('store_name') }} · {{ setting('store_address') }} · {{ setting('store_phone') }} · {{ setting('store_email') }}@if (setting('bin_number')) · BIN {{ setting('bin_number') }}@endif</td>
        <td class="right">Generated {{ now()->format('d M Y, h:i A') }}</td>
    </tr></table>
</div>

<div class="brand-bar"></div>
<table class="header">
    <tr>
        <td>
            <div class="logo">{{ setting('store_name') }}</div>
            <div class="muted small">{{ setting('store_tagline') }}</div>
        </td>
        <td class="right">
            <h1>@yield('title')</h1>
            <div class="muted">@yield('subtitle')</div>
        </td>
    </tr>
</table>

@yield('content')
</body>
</html>
