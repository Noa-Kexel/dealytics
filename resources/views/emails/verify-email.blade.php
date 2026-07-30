@extends('emails.layout')

@section('title', 'Confirmez votre adresse e-mail')

@section('preheader', 'Une dernière étape pour activer votre compte '.config('app.name').'.')

@section('content')
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td bgcolor="#1C1330" style="background-color:#1C1330; border-radius:999px; padding:6px 14px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#A855F7;">
                Bienvenue
            </td>
        </tr>
    </table>

    <h1 class="dl-h1" style="margin:18px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:26px; line-height:34px; font-weight:700; color:#FFFFFF;">
        Confirmez votre adresse e-mail
    </h1>

    <p style="margin:14px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; line-height:24px; color:#C9C5D8;">
        Bonjour {{ $userName }}, votre compte {{ config('app.name') }} est presque prêt.
        Confirmez votre adresse pour activer le suivi des prix et les alertes.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:26px;">
        <tr>
            <td align="center">
                @include('emails.partials.button', ['url' => $url, 'label' => 'Confirmer mon adresse'])
            </td>
        </tr>
    </table>

    <p style="margin:18px 0 0; text-align:center; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#858198;">
        Ce lien expire dans {{ $expiresInMinutes }} minutes.
    </p>

    {{-- Ce qui attend l'utilisateur une fois le compte vérifié --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:28px;">
        <tr>
            <td bgcolor="#161228" style="background-color:#161228; border:1px solid #2A2440; border-radius:14px; padding:22px 24px; font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
                <p style="margin:0 0 12px; font-size:12px; letter-spacing:0.06em; text-transform:uppercase; color:#858198;">
                    Une fois connecté
                </p>
                <p style="margin:0 0 8px; font-size:14px; line-height:22px; color:#C9C5D8;">
                    <span style="color:#A855F7;">&#9679;</span>&nbsp; Comparez les prix d'un jeu sur toutes les boutiques
                </p>
                <p style="margin:0 0 8px; font-size:14px; line-height:22px; color:#C9C5D8;">
                    <span style="color:#EC4899;">&#9679;</span>&nbsp; Ajoutez vos jeux en favoris et suivez leur historique
                </p>
                <p style="margin:0; font-size:14px; line-height:22px; color:#C9C5D8;">
                    <span style="color:#22D3EE;">&#9679;</span>&nbsp; Recevez une alerte dès qu'un prix cible est atteint
                </p>
            </td>
        </tr>
    </table>

    @include('emails.partials.fallback-url', ['url' => $url])
@endsection

@section('subcopy')
    Si vous n'êtes pas à l'origine de cette inscription, ignorez simplement cet e-mail : aucun compte ne sera activé.
@endsection
