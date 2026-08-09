@extends('emails.layout')

@php
    $formattedDate = $sentAt->locale('fr')->isoFormat('D MMMM YYYY [à] HH[h]mm');
@endphp

@section('title', 'Nouveau message de contact : '.$subjectLine)

@section('preheader', $senderName.' vous a écrit : '.$subjectLine)

@section('content')
    {{-- Étiquette --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td bgcolor="#2A1424" style="background-color:#2A1424; border-radius:999px; padding:6px 14px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#EC4899;">
                Formulaire de contact
            </td>
        </tr>
    </table>

    <h1 class="dl-h1" style="margin:18px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:26px; line-height:34px; font-weight:700; color:#FFFFFF;">
        Nouveau message reçu
    </h1>

    <p style="margin:14px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; line-height:24px; color:#C9C5D8;">
        {{ $senderName }} vous a écrit depuis le formulaire de contact du site, le {{ $formattedDate }}.
    </p>

    {{-- Carte du message --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:26px;">
        <tr>
            <td bgcolor="#161228" style="background-color:#161228; border:1px solid #2A2440; border-radius:14px; padding:24px;">

                {{-- Expéditeur --}}
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td class="dl-stack" width="60%" valign="top" style="font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
                            <span style="display:block; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:#858198;">Expéditeur</span>
                            <span style="display:block; margin-top:4px; font-size:17px; line-height:24px; font-weight:600; color:#FFFFFF;">{{ $senderName }}</span>
                            <a href="mailto:{{ $senderEmail }}" style="display:inline-block; margin-top:2px; font-size:13px; line-height:20px; color:#22D3EE; text-decoration:none;">{{ $senderEmail }}</a>
                        </td>
                        <td class="dl-stack-gap" width="1" style="font-size:0; line-height:0;">&nbsp;</td>
                        <td class="dl-stack" width="40%" valign="top" align="right" style="font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
                            @if ($isMember)
                                <span style="display:inline-block; background-color:#1B1533; border:1px solid #3A2E63; border-radius:999px; padding:5px 12px; font-size:11px; font-weight:600; color:#A855F7;">Membre connecté</span>
                            @else
                                <span style="display:inline-block; background-color:#191527; border:1px solid #2A2440; border-radius:999px; padding:5px 12px; font-size:11px; font-weight:600; color:#858198;">Visiteur</span>
                            @endif
                        </td>
                    </tr>
                </table>

                {{-- Séparateur --}}
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:20px;">
                    <tr>
                        <td height="1" bgcolor="#2A2440" style="height:1px; line-height:1px; font-size:0;">&nbsp;</td>
                    </tr>
                </table>

                {{-- Sujet --}}
                <p style="margin:20px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:#858198;">
                    Sujet
                </p>
                <p style="margin:4px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:17px; line-height:24px; font-weight:600; color:#FFFFFF;">
                    {{ $subjectLine }}
                </p>

                {{-- Message --}}
                <p style="margin:20px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:#858198;">
                    Message
                </p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:8px;">
                    <tr>
                        <td bgcolor="#100D1D" style="background-color:#100D1D; border-left:3px solid #A855F7; border-radius:0 8px 8px 0; padding:16px 18px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; line-height:24px; color:#C9C5D8;">
                            {!! nl2br(e($body)) !!}
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:28px;">
        <tr>
            <td align="center">
                @include('emails.partials.button', ['url' => $adminUrl, 'label' => 'Ouvrir dans le panel'])
            </td>
        </tr>
    </table>

    <p style="margin:20px 0 0; text-align:center; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#858198;">
        Répondez directement à cet e-mail pour écrire à {{ $senderName }}.
    </p>
@endsection

@section('subcopy')
    Ce message est également consultable dans le panel d'administration, onglet « Contact ».
@endsection

@section('footer_reason', "Notification interne envoyée depuis le formulaire de contact du site.")
