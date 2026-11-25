@props(['status'])

@php
    $style = \App\Models\Order::STATUS_STYLES[$status] ?? 'bg-slate-50 text-slate-700 ring-slate-500/20';
    $icon = \App\Models\Order::STATUS_ICONS[$status] ?? 'fa-circle';
@endphp

<span {{ $attributes->merge(['class' => "badge capitalize $style"]) }}>
    <i class="fa-solid {{ $icon }} text-[10px]"></i>{{ $status }}
</span>
