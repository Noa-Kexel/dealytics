<div align="center">

# 🎮 Dealytics

**Comparateur et traqueur de prix de jeux vidéo**

Trouvez les meilleures offres gaming, suivez l'évolution des prix, définissez des alertes et gérez votre budget — sur toutes les plateformes.

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white)
![Inertia](https://img.shields.io/badge/Inertia.js-3-9553E9?logo=inertia&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind-4-38BDF8?logo=tailwindcss&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)

</div>

> **Projet TFE** — Application développée dans le cadre d'un travail de fin d'études.

---

## ✨ Fonctionnalités

| Domaine | Détails |
|---|---|
| 🔎 **Recherche & filtres** | Recherche multi-plateformes (Steam, Epic, PlayStation, Xbox), filtres par prix / promo, tri par popularité, prix, réduction ou titre |
| 🏷️ **Fiche jeu détaillée** | Prix par magasin (stores officiels + keyshops), enrichissement (genres, screenshots, description, Metacritic), score qualité/prix `/100` |
| 📈 **Historique des prix** | Vraie courbe historique via IsThereAnyDeal (repli sur des snapshots journaliers), filtres temporels (1 mois / 3 mois / 1 an / tout), plus bas prix observé |
| ❤️ **Favoris** | Sauvegarde des jeux suivis + intégration de la **wishlist Steam** (sans clé API) |
| 🔔 **Alertes de prix** | Prix cible + notifications navigateur quand le seuil est atteint |
| 💶 **Budget & achats** | Suivi du budget gaming mensuel, historique des dépenses, tableau de bord |
| 🔐 **Authentification** | Inscription, vérification e-mail, connexion et **2FA** (Laravel Fortify) |

---

## 🛠️ Stack technique

- **Backend** — Laravel 13 (PHP 8.3), SQLite, Laravel Fortify (auth + 2FA)
- **Frontend** — Vue 3 + Inertia.js 3, TypeScript, Tailwind CSS v4, [shadcn-vue](https://www.shadcn-vue.com/) (reka-ui), Lucide icons
- **Graphiques** — Chart.js + vue-chartjs
- **Build** — Vite 8, Laravel Wayfinder
- **Animations** — animations CSS au montage + directive maison `v-reveal` (scroll reveal type AOS, sur IntersectionObserver)

### Sources de données externes

| Service | Rôle | Clé requise |
|---|---|---|
| **Nexarda** | Source principale : recherche, feed d'accueil, prix multi-stores | ❌ Non |
| **IsThereAnyDeal (ITAD)** | Historique réel des prix | ⚙️ Optionnelle (`ITAD_API_KEY`) |
| **RAWG** | Enrichissement (genres, screenshots, Metacritic…) | ⚙️ Optionnelle (`RAWG_API_KEY`) |
| **Steam** | Wishlist + repli d'enrichissement (profils publics) | ❌ Non |

> Sans clé, l'app reste fonctionnelle : les fiches jeu retombent sur l'API publique Steam et l'historique se construit via les snapshots journaliers.

---

## 🚀 Installation

### Prérequis
- PHP **8.3+** & [Composer](https://getcomposer.org/)
- **Node.js 20+** & npm
- [Laravel Herd](https://herd.laravel.com/) (recommandé sous Windows/macOS) ou `php artisan serve`

### Mise en place

```bash
# 1. Cloner puis installer les dépendances + préparer l'environnement
composer setup
```

Le script `composer setup` enchaîne : `composer install`, copie de `.env`, génération de la clé, migrations, `npm install` et `npm run build`.

> **Installation manuelle** si tu préfères :
> ```bash
> composer install
> cp .env.example .env
> php artisan key:generate
> touch database/database.sqlite
> php artisan migrate
> npm install
> npm run build
> ```

### Configuration

Renseigne les clés API **optionnelles** dans `.env` pour activer l'historique réel et l'enrichissement complet :

```dotenv
ITAD_API_KEY=          # https://isthereanydeal.com/apps/  → historique des prix
RAWG_API_KEY=          # https://rawg.io/apidocs           → genres, screenshots, Metacritic
GGDEALS_API_KEY=       # (optionnel)
```

---

## 💻 Développement

**Avec Laravel Herd** (le site est servi automatiquement, ex. `https://dealytics.test`) :

```bash
npm run dev
```

**Sans Herd** (serveur + worker + Vite en une commande) :

```bash
composer dev
```

Autres scripts utiles :

```bash
npm run build        # build de production
npm run types:check  # vérification TypeScript (vue-tsc)
npm run lint         # ESLint --fix
npm run format       # Prettier
```

---

## ⏱️ Tâches planifiées

L'historique et les alertes reposent sur le scheduler Laravel (voir [`routes/console.php`](routes/console.php)) :

| Commande | Fréquence | Rôle |
|---|---|---|
| `php artisan prices:snapshot` | quotidien (06:00) | Capture le prix du jour pour chaque jeu suivi (favoris + alertes) |
| `php artisan alerts:check` | horaire | Vérifie les alertes et notifie quand le prix cible est atteint |

En production, activer le scheduler via un unique cron :

```cron
* * * * * cd /chemin/vers/dealytics && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🧪 Tests & qualité

```bash
composer test        # Pint (lint PHP) + suite de tests PHPUnit
composer ci:check    # lint + format + types + tests (pipeline complète)
php artisan test     # tests seuls
```

---

## 📁 Structure du projet

```
app/
  Http/Controllers/     # Game, Favorite, PriceAlert, Purchase, Budget, Notification, SteamWishlist
  Services/             # Nexarda, Itad, Rawg, SteamStore, SteamWishlist, GgDeals, PriceAlertChecker
  Console/Commands/     # prices:snapshot, alerts:check
  Models/               # Favorite, PriceAlert, PriceSnapshot, Purchase, BudgetSetting, Notification…
resources/js/
  pages/                # Home, Game/Show, Favorites, GameDashboard, auth/, settings/
  components/            # GameCard, PriceHistoryChart, StorePriceChart, DealBadge…
  composables/           # useFavorites, useAlerts, useBudget, useAppearance…
  directives/            # reveal.ts (v-reveal — scroll reveal)
  layouts/               # DealyticsLayout, AuthLayout, settings/
database/migrations/    # users(+2FA, steam_id), favorites, price_alerts, purchases,
                        # budget_settings, price_snapshots, notifications
```

---

## 🎨 Design

Thème gaming sombre, responsive, avec skeleton loaders et micro-animations.

| Rôle | Couleur |
|---|---|
| Violet (primaire) | `#A855F7` |
| Violet profond | `#7C3AED` |
| Cyan (accent) | `#22D3EE` |
| Rose (accent) | `#EC4899` |
| Fond sombre | `#06040F` |

---

<div align="center">
<sub>© 2026 Dealytics — Projet TFE</sub>
</div>
