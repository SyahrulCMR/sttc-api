<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengajuan Reset Kata Sandi — Admin STTC</title>
</head>
<body>
    <main>
        <h1>Pengajuan Reset Kata Sandi</h1>

        @if (session('success')) <p role="status">{{ session('success') }}</p> @endif
        @if (session('error')) <p role="alert">{{ session('error') }}</p> @endif

        <table>
            <thead>
                <tr><th>NIM/NIDN</th><th>Nama</th><th>Status</th><th>Catatan</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($requests as $req)
                    <tr>
                        <td>{{ $req->identifier }}</td>
                        <td>{{ $req->user?->name }}</td>
                        <td>{{ $req->status }}</td>
                        <td>{{ $req->admin_note }}</td>
                        <td>
                            @if ($req->status === 'pending')
                                <form method="post" action="{{ route('admin.password-requests.approve', $req) }}" style="display:inline">
                                    @csrf <button type="submit">Setujui</button>
                                </form>
                                <form method="post" action="{{ route('admin.password-requests.reject', $req) }}" style="display:inline">
                                    @csrf
                                    <input type="text" name="admin_note" placeholder="Alasan penolakan" required>
                                    <button type="submit">Tolak</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada pengajuan.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{ $requests->links() }}
    </main>
</body>
</html>
