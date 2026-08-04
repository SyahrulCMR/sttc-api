{{-- resources/views/auth/forgot-password.blade.php --}}
@extends('layouts.auth')

@section('title', 'Lupa Kata Sandi')
@section('brand-heading', 'Reset Aman, Akses Kembali Cepat.')
@section('brand-copy', 'Kami akan mengirim tautan reset ke email yang terdaftar pada akun Anda.')

@section('content')
    <h2>Lupa Kata Sandi</h2>
    <p class="subtitle">Masukkan NIM/NIDN atau email terdaftar Anda.</p>

    @if (session('status'))
        <x-auth-alert type="success">{{ session('status') }}</x-auth-alert>
    @endif

    @if ($errors->has('identifier'))
        <x-auth-alert type="error">{{ $errors->first('identifier') }}</x-auth-alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="field">
            <label for="identifier">NIM / NIDN atau Email</label>
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
        <p class="helper-text">Tautan reset berlaku selama 60 menit sejak dikirim.</p>

        <button type="submit" class="btn-primary">Kirim Tautan Reset</button>

        <div class="form-links">
            <a href="{{ route('sso.login') }}">← Kembali ke halaman masuk</a>
        </div>
    </form>
@endsection
