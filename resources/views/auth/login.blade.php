<x-guest-layout>
    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Welcome back</h2>
    <p class="mt-1 text-sm text-slate-500">
        New to {{ setting('store_name') }}? <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:text-brand-700">Create an account</a>
    </p>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="mb-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700" href="{{ route('password.request') }}">Forgot password?</a>
                @endif
            </div>
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <label for="remember_me" class="flex items-center gap-2">
            <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500">
            <span class="text-sm text-slate-600">Keep me signed in</span>
        </label>

        <x-primary-button class="w-full py-2.5">Sign in <i class="fa-solid fa-arrow-right"></i></x-primary-button>
    </form>

    @if (app()->environment('local') || config('app.demo'))
        {{-- Demo accounts created by the database seeder (local environment or APP_DEMO=true only). --}}
        <div class="mt-8 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4" x-data="{ busy: false }">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500"><i class="fa-solid fa-flask mr-1"></i> Demo accounts · password <span class="font-mono normal-case">password</span></p>
            <div class="mt-3 grid grid-cols-3 gap-2">
                @foreach (['admin@example.com' => 'Admin', 'employee@example.com' => 'Employee', 'test@example.com' => 'Customer'] as $email => $label)
                    <button type="button" class="btn-secondary btn-sm" :disabled="busy"
                            @click="busy = true; demoLogin('{{ $email }}')">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <script>
            // The page may have been open for a while (or restored with the Back
            // button), so its CSRF token can be stale. Fetch a fresh one first.
            async function demoLogin(email) {
                const form = document.getElementById('password').form;
                try {
                    const res = await fetch(@js(route('login')), { credentials: 'same-origin', cache: 'no-store' });
                    if (res.redirected && ! new URL(res.url).pathname.startsWith('/login')) {
                        window.location = res.url; // this browser is already signed in
                        return;
                    }
                    const token = (await res.text()).match(/name="_token" value="([^"]+)"/)?.[1];
                    if (token) {
                        form.querySelector('input[name="_token"]').value = token;
                        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
                    }
                } catch (e) {
                    // Offline or blocked: fall through and submit with the token we have.
                }
                document.getElementById('email').value = email;
                document.getElementById('password').value = 'password';
                form.requestSubmit();
            }
        </script>
    @endif
</x-guest-layout>
