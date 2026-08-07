{{--
    Maintenance (php artisan down). Rendue par le middleware avant que
    l'application ne soit complètement démarrée : pas d'Inertia possible ici.
--}}
@include('errors.layout', [
    'code' => 503,
    'title' => 'Maintenance en cours',
    'text' => "Le site est momentanément indisponible, le temps d'une mise à jour. Revenez dans quelques minutes, vos favoris et vos alertes sont intacts.",
    'accent' => '#22D3EE',
    'showButton' => false,
])
