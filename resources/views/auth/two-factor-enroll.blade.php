<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aktifkan Dua Faktor — STTC</title>
</head>
<body>
    <main>
        <h1>Aktifkan Verifikasi Dua Faktor</h1>
        <p>Peran Anda mewajibkan 2FA. Pindai QR ini dengan aplikasi authenticator
           (Google Authenticator, Authy, dll), lalu masukkan kode untuk konfirmasi.</p>

        <img src="{{ $qr }}" alt="QR code 2FA" width="200" height="200">

        <p>Atau masukkan kunci ini secara manual: <code>{{ $secret }}</code></p>

        @if ($errors->any())
            <div role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <form method="post" action="{{ url('/two-factor/enroll') }}">
            @csrf
            <label for="code">Kode 6 digit</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus>
            <button type="submit">Konfirmasi &amp; Aktifkan</button>
        </form>
    </main>
</body>
</html>
