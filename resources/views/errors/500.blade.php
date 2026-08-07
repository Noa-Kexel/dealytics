{{--
    Dernier recours : si même le rendu de la page Inertia « Error » échoue
    (assets absents, base injoignable…), c'est cette page statique qui
    s'affiche plutôt que l'écran par défaut de Laravel.
--}}
@include('errors.layout', [
    'code' => 500,
    'title' => 'Une erreur est survenue',
    'text' => "Le problème vient de chez nous, pas de vous. L'incident a été enregistré et sera examiné au plus vite.",
    'accent' => '#EC4899',
])
