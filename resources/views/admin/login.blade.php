@extends('admin.layout')
@section('title', 'Log in')
@section('body_class', 'login-page')
@section('bare', true)

@section('content')
    <main class="login">
        <form method="POST" action="{{ route('admin.login') }}" class="panel login__card" id="login-form">
            @csrf
            <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="56" height="56" class="login__logo">
            <h1>Owlvix Admin</h1>
            <p class="muted">Log in to see your enquiries.</p>

            @if ($errors->any())
                <div class="alert" role="alert">{{ $errors->first() }}</div>
            @endif

            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </label>
            <label>Password
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="btn">Log in</button>
            @if (\App\Support\Recaptcha::enabled())
                <p class="login__note">Protected by reCAPTCHA · Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy</a> &amp; <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms</a></p>
            @endif
        </form>
    </main>

    @if (\App\Support\Recaptcha::enabled())
        {{-- Invisible reCAPTCHA v3: get a token from Google when the form is sent --}}
        <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode(config('services.recaptcha.site_key')) }}" async defer></script>
        <script>
            (() => {
                const form = document.getElementById('login-form');
                const key = @json(config('services.recaptcha.site_key'));
                let sending = false;
                form.addEventListener('submit', e => {
                    if (sending || !window.grecaptcha) return; // no script: let the server decide
                    e.preventDefault();
                    const btn = form.querySelector('button[type=submit]');
                    btn.disabled = true; btn.textContent = 'Checking…';
                    grecaptcha.ready(() => grecaptcha.execute(key, { action: 'login' }).then(token => {
                        const input = Object.assign(document.createElement('input'), { type: 'hidden', name: 'g-recaptcha-response', value: token });
                        form.appendChild(input);
                        sending = true; form.submit();
                    }, () => { sending = true; form.submit(); }));
                });
            })();
        </script>
    @endif
@endsection
