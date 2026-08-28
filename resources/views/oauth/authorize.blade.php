{{--
    Layar consent OAuth. Untuk STTC semua client adalah first-party internal
    (skipsAuthorization = true), jadi layar ini praktis tidak pernah tampil.
    Dipertahankan sebagai fallback. Diperhalus pada task 1a-9 bila diperlukan.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Otorisasi Aplikasi</title>
</head>
<body>
    <h1>Permintaan Otorisasi</h1>
    <p><strong>{{ $client->name }}</strong> meminta akses ke akun Anda.</p>

    <form method="post" action="{{ route('passport.authorizations.approve') }}">
        @csrf
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <button type="submit">Izinkan</button>
    </form>

    <form method="post" action="{{ route('passport.authorizations.deny') }}">
        @csrf
        @method('DELETE')
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <button type="submit">Tolak</button>
    </form>
</body>
</html>
