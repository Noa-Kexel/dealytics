@php
    // Ligne de pied de page par défaut. Les e-mails adressés à des non-membres
    // (formulaire de contact) la remplacent via @section('footer_reason').
    $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url');
    $defaultFooterReason = 'Vous recevez cet e-mail parce que vous avez un compte sur '
        .'<a href="'.url('/').'" style="color:#A855F7; text-decoration:none;">'.e($appHost).'</a>.';
@endphp
<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="dark">
    <meta name="supported-color-schemes" content="dark">
    <title>@yield('title', config('app.name'))</title>
    {{-- Les styles inline font le gros du travail : ce bloc ne gère que le
         responsive et quelques correctifs clients (Outlook, iOS, Gmail). --}}
    <style>
        html, body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        body { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse !important; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { text-decoration: none; }
        /* iOS transforme automatiquement dates/adresses en liens bleus. */
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; }

        @media only screen and (max-width: 620px) {
            .dl-wrapper { width: 100% !important; }
            .dl-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .dl-h1 { font-size: 22px !important; line-height: 30px !important; }
            .dl-price { font-size: 34px !important; }
            .dl-stack { display: block !important; width: 100% !important; }
            .dl-stack-gap { height: 12px !important; }
            .dl-btn a { display: block !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#06040F; color:#F2F2F2; font-family:'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;">

    {{-- Texte d'aperçu affiché dans la liste des messages, invisible à l'ouverture. --}}
    <div style="display:none; font-size:1px; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; mso-hide:all;">
        @yield('preheader')
        &#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="#06040F" style="background-color:#06040F;">
        <tr>
            <td align="center" style="padding:32px 12px 40px;">

                <table role="presentation" class="dl-wrapper" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px; max-width:600px;">

                    {{-- En-tête / marque --}}
                    <tr>
                        <td align="center" style="padding:0 0 22px;">
                            <a href="{{ url('/') }}" style="text-decoration:none;">
                                <img src="{{ url('/images/logo.png') }}" width="26" height="26" alt="" style="display:inline-block; vertical-align:middle; width:26px; height:26px;">
                                <span style="display:inline-block; vertical-align:middle; margin-left:2px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:19px; font-weight:700; letter-spacing:0.06em; color:#A855F7;">EALYTICS</span>
                            </a>
                        </td>
                    </tr>

                    {{-- Carte principale --}}
                    <tr>
                        <td style="background-color:#0F0C1D; border:1px solid #211D35; border-radius:16px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                {{-- Filet dégradé (les clients qui l'ignorent gardent le violet plein) --}}
                                <tr>
                                    <td height="4" bgcolor="#A855F7" style="height:4px; line-height:4px; font-size:0; background-color:#A855F7; background-image:linear-gradient(90deg, #7C3AED 0%, #A855F7 35%, #EC4899 68%, #22D3EE 100%); border-radius:15px 15px 0 0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td class="dl-pad" style="padding:34px 36px 36px;">
                                        @yield('content')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Pied de page --}}
                    <tr>
                        <td class="dl-pad" style="padding:24px 24px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                @hasSection('subcopy')
                                    <tr>
                                        <td style="padding-bottom:18px; font-size:12px; line-height:19px; color:#858198; font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
                                            @yield('subcopy')
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td align="center" style="padding-bottom:10px; font-size:12px; line-height:20px; color:#858198; font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
                                        <a href="{{ route('legal.notice') }}" style="color:#858198; text-decoration:underline;">Mentions légales</a>
                                        <span style="color:#3A3350;">&nbsp;&middot;&nbsp;</span>
                                        <a href="{{ route('legal.privacy') }}" style="color:#858198; text-decoration:underline;">Confidentialité</a>
                                        <span style="color:#3A3350;">&nbsp;&middot;&nbsp;</span>
                                        <a href="{{ route('legal.terms') }}" style="color:#858198; text-decoration:underline;">CGU</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="font-size:11px; line-height:18px; color:#5C5772; font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
                                        &copy; {{ date('Y') }} {{ config('app.name') }}, suivi de prix des jeux vidéo.<br>
                                        @yield('footer_reason', $defaultFooterReason)
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
