{{--
    Lien de secours affiché sous le bouton, pour les clients qui n'affichent
    pas correctement le bouton ou lorsque l'utilisateur préfère copier l'URL.

    Variables attendues :
    @param string $url
--}}
<p style="margin:26px 0 0; padding-top:20px; border-top:1px solid #211D35; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:12px; line-height:20px; color:#858198;">
    Le bouton ne fonctionne pas ? Copiez-collez ce lien dans votre navigateur :<br>
    <a href="{{ $url }}" style="color:#22D3EE; text-decoration:underline; word-break:break-all;">{{ $url }}</a>
</p>
