# LN Immigration

Site web du cabinet **LN Immigration**, qui accompagne des candidats dans leurs
démarches d'immigration vers le Canada.

Le site remplit deux fonctions :

1. **Vitrine** — présenter le cabinet et ses services.
2. **Dépôt de dossiers** — permettre à un candidat de déposer en ligne son CV,
   ses résultats TCF/TEF, son passeport et ses diplômes, et à l'administratrice
   de traiter ces dossiers depuis un back-office.

Le site est **entièrement en français**. Les candidats se connectent
majoritairement depuis un téléphone Android, en 3G, avec des scans photographiés
de 5 à 10 Mo. Cette contrainte a guidé la plupart des choix techniques.

---

## 1. Stack

| Composant | Version |
|---|---|
| PHP | 8.3+ (`ext-zip` et `ext-fileinfo` requis) |
| Laravel | 13 |
| Base de données | MySQL 8 ou MariaDB 10.11+ |
| Back-office | Filament 5 |
| Front public | Blade + Tailwind CSS 4 (aucune SPA) |
| Espace authentifié | Livewire 4 + Flux (starter kit officiel) |
| Authentification | Laravel Fortify (2FA, réinitialisation, vérification d'e-mail) |
| Upload résilient | FilePond (envoi par tranches) |
| Compression d'images | browser-image-compression |
| Rôles | spatie/laravel-permission |
| Journal d'activité | spatie/laravel-activitylog |
| Sauvegardes | spatie/laravel-backup |
| Qualité | Pint (PSR-12), Larastan niveau 7, Pest |

---

## 2. Installation en développement

```bash
git clone <url-du-depot> LucieSite
cd LucieSite

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Créez la base de données, renseignez les identifiants dans `.env`, puis :

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

Le seeder crée les rôles `admin` et `agent`, les blocs de textes du site, et un
compte de test : **test@example.com / password**.

### Créer un compte du cabinet

L'inscription publique est **volontairement fermée** : aucun candidat ne possède
de compte. Les comptes se créent en console :

```bash
php artisan ln:create-user
php artisan ln:create-user --name="Lucie N." --email=lucie@exemple.cm --role=admin
```

---

## 3. Architecture

### Parcours public

| URL | Rôle |
|---|---|
| `/` | Accueil |
| `/services`, `/a-propos`, `/temoignages`, `/faq`, `/contact` | Pages vitrines |
| `/deposer-mon-dossier` | Formulaire de dépôt |
| `/dossier-recu` | Confirmation, affiche la référence de suivi |
| `/suivre-mon-dossier` | Suivi par référence + e-mail |
| `/mentions-legales`, `/politique-de-confidentialite` | Pages légales |
| `/sitemap.xml` | Plan du site |

### Back-office

Tout est réuni sous **`/admin`**. Il n'existe pas d'autre espace authentifié :
`/dashboard` et `/settings/*` redirigent vers le back-office.

| Section | Contenu | Accès |
|---|---|---|
| Tableau de bord | Dossiers par statut, dossiers du mois, 5 derniers reçus | admin, agent |
| Dossiers | Table, filtres, vue détail, statuts, export ZIP | admin, agent |
| Témoignages | CRUD, photo, publication, réordonnancement | admin |
| Textes du site | Édition de tous les textes publics | admin |
| Comptes et rôles | Création de comptes, attribution des rôles | admin |
| Mon profil / Sécurité | Nom, e-mail, mot de passe, double authentification | admin, agent |

**Rôles :**

- `admin` — tous les droits, y compris les suppressions, les textes du site et
  la gestion des comptes.
- `agent` — consultation des dossiers, changement de statut, notes internes.
  Ni suppression, ni textes du site, ni gestion des comptes.

### Textes du site

Aucun texte visible n'est écrit en dur dans une vue publique. Tout passe par le
helper `content()` et la table `site_contents` :

```blade
{{ content('accueil.hero_titre', 'Titre par défaut') }}
```

Les valeurs par défaut viennent de `SiteContentSeeder`, qui est la source de
vérité initiale. **Le relancer n'écrase jamais un texte modifié depuis le
back-office** : il ajoute seulement les clés absentes.

```bash
php artisan db:seed --class=SiteContentSeeder
```

Le cache est invalidé automatiquement à chaque sauvegarde (`SiteContentObserver`).

### Logique métier

Les contrôleurs orchestrent, la logique vit dans `app/Actions` :

| Classe | Rôle |
|---|---|
| `SubmitApplication` | Enregistre un dossier et ses documents, en transaction |
| `GenerateApplicationReference` | Génère `LN-2026-00147` |
| `BuildApplicationArchive` | Assemble les documents en ZIP |
| `PurgeExpiredApplications` | Efface les dossiers supprimés depuis 90 jours |

---

## 4. Sécurité

Le site héberge des scans de passeports. Les points suivants ne sont pas
négociables.

1. **Les documents des candidats vont sur le disque privé**
   (`storage/app/private`), jamais sur `storage/app/public`. Seule la photo des
   témoignages va sur le disque public.
2. Le téléchargement passe par une **route authentifiée protégée par
   `DocumentPolicy`**. Chaque téléchargement est journalisé avec son auteur.
3. La validation est faite en **Form Requests** avec `mimes`, qui **inspecte le
   contenu réel** du fichier : un exécutable renommé en `.pdf` est rejeté.
4. Les fichiers sont stockés sous un **nom UUID**. Le nom d'origine ne sert
   qu'à l'affichage et n'est jamais utilisé pour écrire sur le disque.
5. Le formulaire est protégé par un **champ honeypot** et un limiteur de
   5 tentatives par minute.
6. `SoftDeletes` sur les dossiers, avec **purge définitive après 90 jours**,
   fichiers compris.
7. L'inscription publique est **désactivée**. Les comptes se créent en console.
8. L'accès au back-office exige un **e-mail vérifié et un rôle**.

### Limiteurs de débit

Ils sont **nommés**, dans `AppServiceProvider::configureRateLimiting()` :

| Limiteur | Plafond |
|---|---|
| `depot` | 5 / minute / IP |
| `suivi` | 5 / minute / IP |
| `televersement` | 300 / minute / IP |

> ⚠️ N'utilisez **jamais** la forme anonyme `throttle:5,1` sur ce projet. Elle
> partage une seule clé `domaine|IP` entre **toutes** les routes : les requêtes
> d'envoi par tranches épuiseraient alors le quota du formulaire de dépôt, et le
> candidat recevrait un 429 au moment de valider son dossier.

---

## 5. Upload résilient

Le formulaire de dépôt fonctionne **de deux manières** :

- **Sans JavaScript** — envoi multipart classique.
- **Avec JavaScript** — FilePond envoie chaque fichier par tranches de 512 ko,
  avec reprise automatique après coupure réseau, et les images sont compressées
  dans le navigateur avant le premier octet envoyé.

Dans les deux cas, les fichiers empruntent **les mêmes règles de validation**
côté serveur.

Les téléversements interrompus sont purgés toutes les heures
(`uploads:purge-temporary`).

---

## 6. Tâches planifiées

Le serveur doit exécuter le planificateur **toutes les minutes** :

```cron
* * * * * cd /chemin/du/site && php artisan schedule:run >> /dev/null 2>&1
```

| Tâche | Fréquence |
|---|---|
| `uploads:purge-temporary` | toutes les heures |
| `backup:clean` | 01h00 |
| `backup:run` | 01h30 |
| `ln:purge-applications` | 03h30 |

`ln:purge-applications` accepte `--dry-run` pour vérifier sans rien effacer.

---

## 7. Sauvegardes

`config/backup.php` sauvegarde la base de données et le contenu de
`storage/app/private` et `storage/app/public`. Le code source n'est pas
sauvegardé : il vit dans Git.

**En production, `BACKUP_DISKS` doit désigner un stockage distant.** Une
sauvegarde qui vit sur le même serveur que les données ne protège de rien.

```dotenv
BACKUP_DISKS=s3
```

Seuls les échecs déclenchent une notification, pour que les vraies alertes ne se
noient pas dans le bruit.

```bash
php artisan backup:run     # sauvegarde manuelle
php artisan backup:list    # état des sauvegardes
```

---

## 8. Qualité

```bash
composer lint          # Pint : corrige le style
composer lint:check    # Pint : vérifie sans corriger
composer types:check   # Larastan niveau 7
php artisan test       # Suite Pest + PHPUnit
composer test          # les trois d'affilée
```

Les trois doivent passer avant tout déploiement.

---

## 9. Déploiement

Voir **[DEPLOIEMENT.md](DEPLOIEMENT.md)** pour la procédure complète et la
checklist de mise en production.
