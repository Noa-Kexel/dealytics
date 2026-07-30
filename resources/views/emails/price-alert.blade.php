@extends('emails.layout')

@php
    $formattedCurrent = number_format($currentPrice, 2, ',', ' ').' €';
    $formattedTarget = number_format($targetPrice, 2, ',', ' ').' €';
    $gap = $targetPrice - $currentPrice;
@endphp

@section('title', 'Prix cible atteint : '.$title)

@section('preheader', $title.' est passé à '.$formattedCurrent.', sous votre objectif de '.$formattedTarget.'.')

@section('content')
    {{-- Étiquette --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td bgcolor="#132630" style="background-color:#132630; border-radius:999px; padding:6px 14px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#22D3EE;">
                Alerte de prix
            </td>
        </tr>
    </table>

    <h1 class="dl-h1" style="margin:18px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:26px; line-height:34px; font-weight:700; color:#FFFFFF;">
        Votre prix cible est atteint
    </h1>

    <p style="margin:14px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; line-height:24px; color:#C9C5D8;">
        Bonjour {{ $userName }}, bonne nouvelle : le jeu que vous surveillez vient de passer
        sous le prix que vous aviez fixé.
    </p>

    {{-- Carte de l'offre --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:26px;">
        <tr>
            <td bgcolor="#161228" style="background-color:#161228; border:1px solid #2A2440; border-radius:14px; padding:24px;">

                <p style="margin:0 0 18px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:17px; line-height:24px; font-weight:600; color:#FFFFFF;">
                    {{ $title }}
                </p>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td class="dl-stack" width="50%" valign="top" style="font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
                            <span style="display:block; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:#858198;">Prix actuel</span>
                            <span class="dl-price" style="display:block; margin-top:4px; font-size:38px; line-height:44px; font-weight:700; color:#A855F7;">{{ $formattedCurrent }}</span>
                        </td>
                        <td class="dl-stack-gap" width="1" style="font-size:0; line-height:0;">&nbsp;</td>
                        <td class="dl-stack" width="50%" valign="top" style="font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
                            <span style="display:block; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:#858198;">Votre objectif</span>
                            <span style="display:block; margin-top:4px; font-size:22px; line-height:44px; font-weight:600; color:#C9C5D8;">{{ $formattedTarget }}</span>
                        </td>
                    </tr>
                </table>

                @if ($gap > 0)
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px;">
                        <tr>
                            <td bgcolor="#132630" style="background-color:#132630; border-radius:8px; padding:8px 12px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:13px; line-height:18px; font-weight:600; color:#22D3EE;">
                                {{ number_format($gap, 2, ',', ' ') }} € sous votre objectif
                            </td>
                        </tr>
                    </table>
                @endif

            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:28px;">
        <tr>
            <td align="center">
                @include('emails.partials.button', ['url' => $gameUrl, 'label' => 'Voir l\'offre'])
            </td>
        </tr>
    </table>

    <p style="margin:20px 0 0; text-align:center; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#858198;">
        Les prix évoluent vite : vérifiez la disponibilité de l'offre avant d'acheter.
    </p>
@endsection

@section('subcopy')
    Vous recevez ce message parce que vous avez créé une alerte de prix sur {{ $title }}.
    <a href="{{ $alertsUrl }}" style="color:#A855F7; text-decoration:underline;">Gérer mes alertes</a>
@endsection
