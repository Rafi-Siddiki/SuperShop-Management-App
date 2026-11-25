{{-- Customers see the profile inside the shop; staff see it inside their panel. --}}
<x-dynamic-component :component="$user->isCustomer() ? 'shop-layout' : 'panel-layout'" title="Profile" subtitle="Manage your account details and password">
    <div @class(['space-y-6', 'mx-auto max-w-3xl px-4 sm:px-6 lg:px-8' => $user->isCustomer(), 'max-w-3xl' => ! $user->isCustomer()])>
        @if ($user->isCustomer())
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Profile</h1>
                <p class="mt-1 text-sm text-slate-500">Manage your account details and password.</p>
            </div>
        @endif

        <div class="card flex items-center gap-4 p-6">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-lg font-bold text-white">{{ $user->initials }}</span>
            <div>
                <p class="text-lg font-bold text-slate-900">{{ $user->name }}</p>
                <p class="text-sm text-slate-500">{{ $user->email }} · <span class="capitalize">{{ $user->role }}</span> since {{ $user->created_at->format('M Y') }}</p>
            </div>
        </div>

        <div class="card p-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="card p-6">
            @include('profile.partials.update-password-form')
        </div>

        <div class="card border-rose-100 p-6">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-dynamic-component>
