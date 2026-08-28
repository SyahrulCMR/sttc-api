<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pilih Konteks Role — STTC</title>
</head>
<body>
    <main>
        <h1>Masuk sebagai</h1>
        <p>Akun Anda memiliki lebih dari satu peran. Pilih peran untuk sesi ini.</p>

        <form method="post" action="{{ url('/select-role') }}">
            @csrf
            <ul>
                @foreach ($roles as $role)
                    <li>
                        <button type="submit" name="role" value="{{ $role->slug }}">
                            {{ $role->name }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </form>
    </main>
</body>
</html>
