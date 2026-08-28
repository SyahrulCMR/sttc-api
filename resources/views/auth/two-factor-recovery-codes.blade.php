<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kode Pemulihan — STTC</title>
</head>
<body>
    <main>
        <h1>Simpan Kode Pemulihan Anda</h1>
        <p><strong>Kode ini hanya ditampilkan sekali.</strong> Simpan di tempat aman —
           setiap kode dapat dipakai satu kali bila Anda kehilangan perangkat authenticator.</p>

        <ul>
            @foreach ($codes as $code)
                <li><code>{{ $code }}</code></li>
            @endforeach
        </ul>

        <form method="post" action="{{ url('/two-factor/recovery-codes') }}">
            @csrf
            <button type="submit">Saya sudah menyimpannya — Lanjutkan</button>
        </form>
    </main>
</body>
</html>
