@extends('admin.layout')
@section('title', 'Log in')
@section('body_class', 'login-page')
@section('bare', true)

@section('content')
    <main class="login">
        <form method="POST" action="{{ route('admin.login') }}" class="panel login__card">
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
        </form>
    </main>
@endsection
