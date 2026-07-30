@extends('emails.layout')

@section('title', 'Réinitialisation de votre mot de passe')

@section('preheader', 'Choisissez un nouveau mot de passe pour votre compte '.config('app.name').'.')

@section('content')
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td bgcolor="#1C1330" style="background-color:#1C1330; border-radius:999px; padding:6px 14px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#A855F7;">
                Sécurité
            </td>
        </tr>
    </table>

    <h1 class="dl-h1" style="margin:18px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:26px; line-height:34px; font-weight:700; color:#FFFFFF;">
        Réinitialisation de votre mot de passe
    </h1>

    <p style="margin:14px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; line-height:24px; color:#C9C5D8;">
        Bonjour {{ $userName }}, nous avons reçu une demande de réinitialisation du mot de passe
        associé à <span style="color:#FFFFFF;">{{ $email }}</span>.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:26px;">
        <tr>
            <td align="center">
                @include('emails.partials.button', ['url' => $url, 'label' => 'Choisir un nouveau mot de passe'])
            </td>
        </tr>
    </table>

    <p style="margin:18px 0 0; text-align:center; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#858198;">
        Ce lien expire dans {{ $expiresInMinutes }} minutes et ne peut être utilisé qu'une seule fois.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:28px;">
        <tr>
            <td bgcolor="#1A1020" style="background-color:#1A1020; border:1px solid #3A1F33; border-radius:14px; padding:18px 20px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:13px; line-height:21px; color:#C9C5D8;">
                <span style="color:#EC4899; font-weight:700;">Vous n'avez rien demandé ?</span>
                Ignorez cet e-mail : votre mot de passe actuel reste valable tant que ce lien n'est pas utilisé.
            </td>
        </tr>
    </table>

    @include('emails.partials.fallback-url', ['url' => $url])
@endsection

@section('subcopy')
    Par sécurité, ne transférez jamais cet e-mail : toute personne disposant de ce lien pourrait modifier votre mot de passe.
@endsection
