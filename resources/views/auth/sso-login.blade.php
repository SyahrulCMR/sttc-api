{{-- resources/views/auth/sso-login.blade.php --}}
@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <h2>Masuk ke Portal</h2>
    <p class="subtitle">Gunakan NIM/NIDN dan kata sandi utama Anda untuk melanjutkan ke {{ ucfirst($app) }}.</p>

    @if ($errors->has('identifier'))
        <x-auth-alert type="error">
            {{ $errors->first('identifier') }}
        </x-auth-alert>
    @endif

    @if (session('status'))
        <x-auth-alert type="success">{{ session('status') }}</x-auth-alert>
    @endif

    <form method="POST" action="{{ route('sso.login.submit') }}" novalidate>
        @csrf
        <input type="hidden" name="app" value="{{ $app }}">

        <div class="field">
            <label for="identifier">NIM / NIDN</label>
            <input
                type="text"
                id="identifier"
                name="identifier"
                value="{{ old('identifier') }}"
                aria-invalid="{{ $errors->has('identifier') ? 'true' : 'false' }}"
                autofocus
                required
            >
        </div>

        <div class="field">
            <label for="password">Kata Sandi</label>
            <input
                type="password"
                id="password"
                name="password"
                aria-invalid="{{ $errors->has('identifier') ? 'true' : 'false' }}"
                required
            >
        </div>

        <button type="submit" class="btn-primary">Masuk</button>

        <div class="form-links">
            <a href="#">Lupa kata sandi?</a>
            <span style="color:#8FA79B;">v1 · SSO STT Cipasung</span>
        </div>
    </form>
@endsection
