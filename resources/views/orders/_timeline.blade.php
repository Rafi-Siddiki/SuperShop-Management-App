{{-- Audit trail of an order's status changes. --}}
<ol class="relative space-y-4 border-l-2 border-slate-100 pl-5">
    @foreach ($order->histories as $entry)
        <li class="relative">
            <span class="absolute -left-[1.68rem] top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-white ring-2 ring-slate-200">
                <i class="fa-solid {{ \App\Models\Order::STATUS_ICONS[$entry->to_status] ?? 'fa-circle' }} text-[9px] text-slate-500"></i>
            </span>
            <p class="text-sm">
                <x-status-badge :status="$entry->to_status" />
                @if ($showUsers ?? false)
                    <span class="ml-1 text-slate-500">by {{ $entry->user?->name ?? 'system' }}</span>
                @endif
            </p>
            <p class="mt-1 text-xs text-slate-500">{{ $entry->created_at->format('d M Y, h:i A') }}@if ($entry->note) · {{ $entry->note }}@endif</p>
        </li>
    @endforeach
</ol>
