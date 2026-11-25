<x-panel-layout title="Staff" subtitle="Admins manage everything; employees handle orders and can view stock">
    <div class="grid gap-6 xl:grid-cols-5">
        <div class="card overflow-hidden xl:col-span-3">
            <div class="card-header">
                <h2 class="card-title">Team members</h2>
                <span class="text-xs text-slate-500">{{ $staff->count() }} accounts</span>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($staff as $member)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span @class(['flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold text-white',
                            'bg-gradient-to-br from-brand-500 to-accent-500' => $member->isAdmin(),
                            'bg-gradient-to-br from-sky-500 to-indigo-500' => $member->isEmployee()])>{{ $member->initials }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-900">
                                {{ $member->name }}
                                @if ($member->is(auth()->user()))<span class="text-xs font-normal text-slate-400">(you)</span>@endif
                            </p>
                            <p class="truncate text-xs text-slate-500">{{ $member->email }}</p>
                        </div>
                        <span @class(['badge capitalize',
                            'bg-brand-50 text-brand-700 ring-brand-600/20' => $member->isAdmin(),
                            'bg-sky-50 text-sky-700 ring-sky-600/20' => $member->isEmployee()])>{{ $member->role }}</span>
                        @unless ($member->is(auth()->user()))
                            <form method="POST" action="{{ route('staff.destroy', $member) }}" onsubmit="return confirm('Delete {{ addslashes($member->name) }}\'s account?')">
                                @csrf @method('DELETE')
                                <button class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50" title="Delete account"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>

        <form action="{{ route('staff.store') }}" method="POST" class="card h-fit p-6 xl:col-span-2">
            @csrf
            <h2 class="card-title mb-5">Add a staff account</h2>
            <div class="space-y-4">
                <div>
                    <label for="name" class="form-label">Full name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required class="form-input">
                    @error('name')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required class="form-input">
                    @error('email')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" required autocomplete="new-password" class="form-input">
                        @error('password')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="form-label">Confirm</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="form-input">
                    </div>
                </div>
                <div>
                    <span class="form-label">Role</span>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach (['employee' => ['fa-user-tie', 'Orders & stock view'], 'admin' => ['fa-user-shield', 'Full access']] as $role => [$icon, $desc])
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50">
                                <input type="radio" name="role" value="{{ $role }}" class="mt-1 text-brand-600 focus:ring-brand-500" @checked(old('role', 'employee') === $role)>
                                <span>
                                    <span class="block text-sm font-semibold capitalize"><i class="fa-solid {{ $icon }} mr-1 text-slate-400"></i>{{ $role }}</span>
                                    <span class="text-xs text-slate-500">{{ $desc }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('role')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <button class="btn-primary mt-6 w-full"><i class="fa-solid fa-user-plus"></i> Create account</button>
        </form>
    </div>
</x-panel-layout>
