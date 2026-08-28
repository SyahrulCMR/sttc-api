<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — STTC</title>
</head>
<body>
    <main>
        <h1>Masuk ke STTC</h1>

        @if ($errors->any())
            <div role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ url('/login') }}">
            @csrf

            <label for="identifier">NIM / NIDN</label>
            <input id="identifier" name="identifier" type="text" value="{{ old('identifier') }}" required autofocus>

            <label for="password">Kata Sandi</label>
            <input id="password" name="password" type="password" required>

            <button type="submit">Masuk</button>
        </form>

        <p><a href="{{ route('password.request') }}">Lupa kata sandi?</a></p>
    </main>
</body>
</html>
