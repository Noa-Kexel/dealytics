{{--
    Page d'erreur autonome, volontairement sans Vite ni Inertia : elle doit
    s'afficher même quand l'application ne peut plus démarrer (maintenance,
    assets absents, base injoignable). Tout est en styles en ligne.

    @param string $code    Code HTTP affiché en grand
    @param string $title   Titre de la page
    @param string $text    Phrase d'explication
    @param string $accent  Couleur d'accent (hex)
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} : {{ $title }} | {{ config('app.name') }}</title>
    <style>
        *{ box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background-color: #06040F;
            background-image: radial-gradient(ellipse 80% 60% at 50% -10%, rgba(124,58,237,0.22), transparent 70%);
            color: #F2F2F2;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
            text-align: center;
        }
        .card { max-width: 460px; }
        .brand { font-size: 19px; font-weight: 700; letter-spacing: 0.06em; color: #A855F7; text-decoration: none; }
        .code {
            margin: 28px 0 0;
            font-size: 76px;
            font-weight: 700;
            line-height: 1;
            background-image: linear-gradient(120deg, #A855F7 0%, #EC4899 55%, #22D3EE 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        h1 { margin: 14px 0 0; font-size: 24px; line-height: 32px; font-weight: 700; color: #FFFFFF; }
        p { margin: 12px 0 0; font-size: 15px; line-height: 24px; color: #C9C5D8; }
        .btn {
            display: inline-block;
            margin-top: 28px;
            padding: 13px 30px;
            border-radius: 10px;
            background-image: linear-gradient(135deg, #A855F7 0%, #7C3AED 100%);
            font-size: 15px;
            font-weight: 600;
            color: #FFFFFF;
            text-decoration: none;
        }
        .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: {{ $accent }}; margin-right: 8px; vertical-align: middle; }
        .note { margin-top: 26px; font-size: 12px; color: #5C5772; }
        @media (max-width: 480px) { .code { font-size: 58px; } h1 { font-size: 20px; } }
    </style>
</head>
<body>
    <div class="card">
        <a href="{{ url('/') }}" class="brand">DEALYTICS</a>

        <p class="code">{{ $code }}</p>
        <h1><span class="dot"></span>{{ $title }}</h1>
        <p>{{ $text }}</p>

        @if ($showButton ?? true)
            <a href="{{ url('/') }}" class="btn">Retour à l'accueil</a>
        @endif

        <p class="note">{{ config('app.name') }}, suivi de prix des jeux vidéo</p>
    </div>
</body>
</html>
