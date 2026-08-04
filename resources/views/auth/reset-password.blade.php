{{-- resources/views/auth/reset-password.blade.php --}}
@extends('layouts.auth')

@section('title', 'Atur Ulang Kata Sandi')
@section('brand-heading', 'Satu Kata Sandi Baru, Semua Aplikasi Ikut Update.')
@section('brand-copy', 'Kata sandi ini berlaku untuk Siakad, LMS, dan Blog karena kredensial Anda terpusat di SSO.')

@section('content')
    <h2>Buat Kata Sandi Baru</h2>
    <p class="subtitle">Untuk akun {{ $email ?? old('email') }}</p>

    @if ($errors->any())
        <x-auth-alert type="error">
            {{ $errors->first() }}
        </x-auth-alert>
    @endif

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email ?? old('email') }}">

        <div class="field">
            <label for="password">Kata Sandi Baru</label>
            <input
                type="password"
                id="password"
                name="password"
                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                required
                minlength="8"
            >
        </div>
        <p class="helper-text">Minimal 8 karakter, kombinasikan huruf dan angka.</p>

        <div class="field">
            <label for="password_confirmation">Konfirmasi Kata Sandi</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                required
            >
        </div>

        <button type="submit" class="btn-primary">Simpan Kata Sandi</button>
    </form>
@endsection
