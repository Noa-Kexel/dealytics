{{--
    Bouton « bulletproof » : un tableau plutôt qu'un <a> stylé, plus un
    rectangle VML pour Outlook (Word) qui ignore padding et border-radius.

    Variables attendues :
    @param string $url   Destination du bouton
    @param string $label Libellé affiché
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="dl-btn" style="margin:0 auto;">
    <tr>
        <td align="center" bgcolor="#7C3AED" style="border-radius:10px; background-color:#7C3AED; background-image:linear-gradient(135deg, #A855F7 0%, #7C3AED 100%);">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:46px; v-text-anchor:middle; width:280px;" arcsize="22%" stroke="f" fillcolor="#7C3AED">
                <w:anchorlock/>
                <center style="color:#FFFFFF; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; font-weight:600;">{{ $label }}</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso] -->
            <a href="{{ $url }}" target="_blank" rel="noopener" style="display:inline-block; padding:14px 34px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; font-weight:600; line-height:18px; color:#FFFFFF; text-decoration:none; border-radius:10px;">
                {{ $label }}
            </a>
            <!--<![endif]-->
        </td>
    </tr>
</table>
