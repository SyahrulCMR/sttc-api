{{-- resources/views/layouts/auth.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Masuk') — Portal SSO STT Cipasung</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --stt-green-900: #0B4D33;
            --stt-green-700: #0F7A4C;
            --stt-green-500: #2FA873;
            --stt-mint-100: #EAF7F1;
            --stt-ink: #12241C;
            --stt-red-600: #C0392B;
            --stt-white: #FFFFFF;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--stt-ink);
            background: var(--stt-mint-100);
        }
        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr;
        }
        @media (min-width: 900px) {
            .auth-shell { grid-template-columns: 42% 58%; }
        }
        /* Panel identitas */
        .auth-brand {
            position: relative;
            background: linear-gradient(180deg, var(--stt-green-900) 0%, var(--stt-green-700) 100%);
            color: var(--stt-white);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            min-height: 260px;
        }
        .auth-brand__mark {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Fraunces', serif;
            font-weight: 700;
            font-size: 20px;
            letter-spacing: 0.02em;
            z-index: 2;
        }
        .auth-brand__mark svg { flex-shrink: 0; }
        .auth-brand__tagline {
            z-index: 2;
            max-width: 340px;
        }
        .auth-brand__tagline h1 {
            font-family: 'Fraunces', serif;
            font-size: clamp(24px, 3vw, 32px);
            font-weight: 600;
            line-height: 1.25;
            margin: 0 0 12px;
        }
        .auth-brand__tagline p {
            font-size: 14px;
            line-height: 1.6;
            color: rgba(255,255,255,0.78);
            margin: 0;
        }
        /* Motif gapura berlapis, signature element */
        .auth-brand__arches {
            position: absolute;
            right: -60px;
            bottom: -40px;
            width: 380px;
            height: 380px;
            opacity: 0.35;
            z-index: 1;
        }
        /* Panel form */
        .auth-form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
            background: var(--stt-white);
        }
        .auth-form-card { width: 100%; max-width: 380px; }
        .auth-form-card h2 {
            font-family: 'Fraunces', serif;
            font-size: 26px;
            font-weight: 600;
            margin: 0 0 6px;
            color: var(--stt-green-900);
        }
        .auth-form-card .subtitle {
            font-size: 14px;
            color: #5B6F65;
            margin: 0 0 28px;
        }
        .field { margin-bottom: 18px; }
        .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--stt-ink);
        }
        .field input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #D6E6DD;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            background: var(--stt-mint-100);
            color: var(--stt-ink);
            transition: border-color .15s, background .15s;
        }
        .field input:focus {
            outline: none;
            border-color: var(--stt-green-500);
            background: var(--stt-white);
            box-shadow: 0 0 0 3px rgba(47,168,115,0.15);
        }
        .field input[aria-invalid="true"] {
            border-color: var(--stt-red-600);
            background: #FDF1EF;
        }
        .btn-primary {
            width: 100%;
            padding: 13px 16px;
            border: none;
            border-radius: 10px;
            background: var(--stt-green-700);
            color: var(--stt-white);
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }
        .btn-primary:hover { background: var(--stt-green-500); }
        .btn-primary:focus-visible { outline: 3px solid #B8E3D1; outline-offset: 2px; }
        .form-links {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            font-size: 13px;
        }
        .form-links a { color: var(--stt-green-700); text-decoration: none; font-weight: 600; }
        .form-links a:hover { text-decoration: underline; }
        /* Alert */
        .auth-alert {
            display: flex;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13.5px;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .auth-alert--error { background: #FDF1EF; color: var(--stt-red-600); border: 1px solid #F3C7C0; }
        .auth-alert--success { background: #EAF7F1; color: var(--stt-green-900); border: 1px solid #BFE5D2; }
        .auth-alert--warning { background: #FFF6E9; color: #8A5A00; border: 1px solid #F2DBA8; }
        .field-error {
            font-size: 12.5px;
            color: var(--stt-red-600);
            margin-top: 6px;
        }
        .helper-text {
            font-size: 12.5px;
            color: #5B6F65;
            margin-top: -8px;
            margin-bottom: 18px;
        }
    </style>
</head>
<body>
    <div class="auth-shell">
        <div class="auth-brand">
            <div class="auth-brand__mark">
                <svg width="28" height="28" viewBox="0 0 100 100" fill="none">
                    <path d="M50 8 L92 88 H8 Z" fill="#FFFFFF" fill-opacity="0.95"/>
                    <path d="M50 8 L92 88 H8 Z" fill="none" stroke="#0B4D33" stroke-width="2"/>
                </svg>
                STT CIPASUNG
            </div>

            <svg class="auth-brand__arches" viewBox="0 0 380 380" fill="none">
                <path d="M190 380 C190 260 260 220 260 130 C260 60 230 20 190 20 C150 20 120 60 120 130 C120 220 190 260 190 380 Z" stroke="#FFFFFF" stroke-width="2"/>
                <path d="M190 380 C190 280 240 245 240 165 C240 100 218 65 190 65 C162 65 140 100 140 165 C140 245 190 280 190 380 Z" stroke="#FFFFFF" stroke-width="2"/>
                <path d="M190 380 C190 300 220 270 220 200 C220 140 206 110 190 110 C174 110 160 140 160 200 C160 270 190 300 190 380 Z" stroke="#FFFFFF" stroke-width="2"/>
            </svg>

            <div class="auth-brand__tagline">
                <h1>@yield('brand-heading', 'Satu Gerbang, Seluruh Ekosistem Kampus.')</h1>
                <p>@yield('brand-copy', 'Masuk sekali untuk mengakses Siakad, LMS, dan Blog STT Cipasung tanpa login berulang.')</p>
            </div>
        </div>

        <div class="auth-form-panel">
            <div class="auth-form-card">
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
