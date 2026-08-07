@extends('emails.layout')

@php
    $formattedDate = $sentAt->locale('fr')->isoFormat('D MMMM YYYY [à] HH[h]mm');
@endphp

@section('title', 'Nous avons bien reçu votre message')

@section('preheader', 'Votre message « '.$subjectLine.' » nous est bien parvenu.')

@section('content')
    {{-- Étiquette --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td bgcolor="#132630" style="background-color:#132630; border-radius:999px; padding:6px 14px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#22D3EE;">
                Message bien reçu
            </td>
        </tr>
    </table>

    <h1 class="dl-h1" style="margin:18px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:26px; line-height:34px; font-weight:700; color:#FFFFFF;">
        Merci, votre message est arrivé
    </h1>

    <p style="margin:14px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; line-height:24px; color:#C9C5D8;">
        Bonjour {{ $senderName }}, nous avons bien reçu votre demande le {{ $formattedDate }}.
        Nous y répondrons dès que possible, à cette même adresse e-mail.
    </p>

    <p style="margin:14px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; line-height:24px; color:#C9C5D8;">
        Cet e-mail est un simple accusé de réception : vous n'avez rien d'autre à faire.
    </p>

    {{-- Rappel du message --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:26px;">
        <tr>
            <td bgcolor="#161228" style="background-color:#161228; border:1px solid #2A2440; border-radius:14px; padding:24px;">

                <p style="margin:0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:#858198;">
                    Votre sujet
                </p>
                <p style="margin:4px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:17px; line-height:24px; font-weight:600; color:#FFFFFF;">
                    {{ $subjectLine }}
                </p>

                <p style="margin:20px 0 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:#858198;">
                    Votre message
                </p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:8px;">
                    <tr>
                        <td bgcolor="#100D1D" style="background-color:#100D1D; border-left:3px solid #22D3EE; border-radius:0 8px 8px 0; padding:16px 18px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; line-height:24px; color:#C9C5D8;">
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
                @include('emails.partials.button', ['url' => $homeUrl, 'label' => 'Retourner sur le site'])
            </td>
        </tr>
    </table>

    <p style="margin:20px 0 0; text-align:center; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#858198;">
        En attendant, la <a href="{{ $faqUrl }}" style="color:#A855F7; text-decoration:underline;">FAQ</a>
        répond peut-être déjà à votre question.
    </p>
@endsection

@section('subcopy')
    Inutile de répondre à cet e-mail automatique : notre réponse vous parviendra dans un message séparé.
@endsection

@section('footer_reason', "Vous recevez cet e-mail parce qu'un message a été envoyé depuis le formulaire de contact du site avec cette adresse. Si ce n'était pas vous, ignorez simplement ce message.")
