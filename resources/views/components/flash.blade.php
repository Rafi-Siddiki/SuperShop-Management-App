@props(['showErrors' => false, 'withMessages' => true])

@php
    $messages = $withMessages ? collect([
        'success' => session('success') ?? session('message'),
        'error' => session('error'),
    ])->filter() : collect();
@endphp

@foreach ($messages as $type => $message)
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition.opacity.duration.300ms
         @class([
             'mb-5 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-sm',
             'border-emerald-200 bg-emerald-50 text-emerald-800' => $type === 'success',
             'border-rose-200 bg-rose-50 text-rose-800' => $type === 'error',
         ])>
        <i @class(['fa-solid mt-0.5', 'fa-circle-check text-emerald-500' => $type === 'success', 'fa-circle-exclamation text-rose-500' => $type === 'error'])></i>
        <p class="flex-1 font-medium">{{ $message }}</p>
        <button type="button" @click="show = false" class="opacity-60 hover:opacity-100" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
    </div>
@endforeach

@if ($errors->any() && $showErrors)
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm">
        <p class="font-semibold"><i class="fa-solid fa-triangle-exclamation mr-1.5 text-rose-500"></i>Please fix the following:</p>
        <ul class="mt-1.5 list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
