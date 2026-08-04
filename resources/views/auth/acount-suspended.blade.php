{{-- resources/views/auth/account-suspended.blade.php --}}
@extends('layouts.auth')

@section('title', 'Akun Ditangguhkan')
@section('brand-heading', 'Akses Sedang Ditinjau.')
@section('brand-copy', 'Tim administrator akan menghubungi Anda terkait status akun.')

@section('content')
    <h2>Akun Ditangguhkan</h2>
    <x-auth-alert type="warning">
        Akun <strong>{{ $identifier ?? '' }}</strong> sedang ditangguhkan dan tidak dapat digunakan untuk masuk ke sistem manapun (Siakad, LMS, Blog).
    </x-auth-alert>
    <p class="subtitle">Silakan hubungi administrator sistem di <strong>admin@sttcipasung.ac.id</strong> untuk informasi lebih lanjut.</p>

    <a href="{{ route('sso.login') }}" class="btn-primary" style="display:block;text-align:center;text-decoration:none;box-sizing:border-box;">
        Kembali ke Halaman Masuk
    </a>
@endsection
