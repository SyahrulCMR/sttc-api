<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Dua Faktor — STTC</title>
</head>
<body>
    <main>
        <h1>Verifikasi Dua Faktor</h1>
        <p>Masukkan kode 6 digit dari aplikasi authenticator Anda.</p>

        @if ($errors->any())
            <div role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <form method="post" action="{{ url('/two-factor/challenge') }}">
            @csrf
            <label for="code">Kode 2FA</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus>
            <button type="submit">Verifikasi</button>
        </form>

        <details>
            <summary>Gunakan kode pemulihan</summary>
            <form method="post" action="{{ url('/two-factor/challenge') }}">
                @csrf
                <label for="recovery_code">Kode pemulihan</label>
                <input id="recovery_code" name="recovery_code">
                <button type="submit">Verifikasi</button>
            </form>
        </details>
    </main>
</body>
</html>
