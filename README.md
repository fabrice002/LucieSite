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
php artisan storage:link
npm run build
php artisan serve
```

> `storage:link` crée le lien `public/storage`. Sans lui, **les photos des
> témoignages ne s'affichent pas** : elles sont bien téléversées, mais rien ne
> les sert. C'est le seul disque exposé publiquement ; les documents des
> candidats, eux, restent sur le disque privé.

Le seeder crée les rôles `admin` et `agent`, les blocs de textes du site, et un
compte de test : **test@example.com / password**.

### Créer un compte du cabinet

L'inscription publique est **volontairement fermée** : aucun candidat ne possède
de compte. Les comptes se créent en console :

```bash
# Interactif : le mot de passe est demandé sans être affiché
php artisan ln:create-user

# Scripté : mot de passe généré et affiché une fois
php artisan ln:create-user --name="Lucie N." --email=lucie@exemple.cm --role=admin --generate
```

Le compte peut aussi être créé depuis **Comptes et rôles** dans le back-office.

Dans les deux cas :

- un **e-mail de bienvenue** part en file d'attente — le worker doit tourner,
  sinon rien n'est envoyé ;
- le mot de passe est **provisoire** : à la première connexion, son titulaire est
  redirigé vers un écran de changement et ne peut rien faire d'autre avant
  d'en avoir choisi un nouveau ;
- le mot de passe n'est **jamais** transmis par e-mail. Il se communique de vive
  voix ou par un canal sûr.

> ⚠️ Les notifications sont mises en file d'attente. En développement, lancez
> `php artisan queue:work` dans un second terminal, sinon **aucun e-mail ne
> part** — ni la bienvenue, ni les accusés de réception de dossier.

### Vérifier la configuration e-mail

```bash
php artisan ln:test-mail votre.adresse@exemple.com
```

Cette commande envoie **immédiatement, sans passer par la file**. Elle sépare
donc les deux causes possibles d'un e-mail qui n'arrive pas :

- **elle réussit** → la configuration SMTP est bonne ; si les e-mails du site
  n'arrivent toujours pas, c'est le worker qui ne tourne pas ;
- **elle échoue** → elle affiche l'erreur du serveur SMTP et ce qu'il faut
  corriger.

Elle affiche aussi la configuration réellement utilisée et signale les deux
pièges classiques : `MAIL_MAILER=log` (rien ne part) et le sandbox Mailtrap
(qui ne délivre jamais vers une vraie boîte).

**Gmail** exige un *mot de passe d'application* — le mot de passe du compte est
refusé avec `534-5.7.9 Application-specific password required`. Il se génère sur
[myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords),
après activation de la validation en deux étapes.

Après toute modification de `.env` : `php artisan config:clear`, **et redémarrez
le worker** — il garde l'ancienne configuration en mémoire.

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

### Changer le logo

Tout se règle dans **`config/brand.php`**, un seul endroit. Le logo apparaît
alors partout : en-tête et pied du site public, page de connexion, back-office,
et icône de l'onglet du navigateur.

**Avec le logo définitif de la cliente :**

1. Déposer le fichier dans `public/images/`, par exemple `public/images/logo.svg`
2. Renseigner le chemin :

```php
// config/brand.php
'logo' => 'images/logo.svg',
```

ou, sans toucher au code, dans `.env` :

```dotenv
BRAND_LOGO=images/logo.svg
```

3. Régénérer l'icône d'onglet :

```bash
php artisan ln:generate-icons
```

**Sans logo définitif**, un monogramme « LN » est utilisé. Il est tracé depuis
`config/brand.php` et suit le thème clair ou sombre, ce qu'un fichier image ne
sait pas faire. Ses couleurs se règlent au même endroit :

```php
'icone_fond' => '#1e40af',
'icone_trait' => '#ffffff',
```

La commande `ln:generate-icons` produit `favicon.svg`, `favicon.ico` et
`apple-touch-icon.png` à partir de ces mêmes valeurs — la marque reste donc
identique dans les pages et dans l'onglet.

> Pensez à vider le cache du navigateur : les favicons sont conservés très
> longtemps, et l'ancienne icône peut sembler persister.

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
| `PurgeExpiredApplications` | Efface les dossiers supprimés ou sans activité |
| `NotifyApplicant` | Informe le candidat : statut, message, alerte e-mail |

### Informer un candidat

Depuis la fiche d'un dossier, **« Informer le candidat »** ouvre une fenêtre où
l'on choisit le nouveau statut, un message à son intention, et si une alerte
e-mail doit partir.

Le principe de circulation de l'information est délibéré :

- **L'e-mail n'est qu'une alerte.** Il contient la référence, le nouveau statut
  et un lien vers la page de suivi. Il ne contient **jamais** le message rédigé
  par le cabinet, aucun document, aucune note interne, et aucun lien de
  connexion automatique.
- **Le message se lit uniquement sur la plateforme**, après saisie de la
  référence et de l'adresse e-mail sur `/suivre-mon-dossier`.

Une boîte e-mail peut être partagée, consultée sur un téléphone prêté ou
compromise : le contenu sensible reste derrière cette vérification.

Les messages sont conservés dans la table **`application_updates`**.
`applications.status` reste la source de vérité du statut courant ; chaque mise
à jour porteuse d'un statut le met à jour dans la même transaction.
`internal_notes` reste strictement privé et n'apparaît nulle part côté candidat.

Les modèles de message proposés se modifient dans **Textes du site**, bloc
« Suivre mon dossier », sous les clés `modele_*`. Les textes de l'e-mail sont
dans le bloc « E-mail — Votre dossier a été mis à jour ».

> Un bandeau rouge apparaît sur le tableau de bord des comptes `admin` lorsqu'un
> e-mail attend depuis plus de dix minutes : c'est le signe que le worker est
> arrêté et que les candidats ne sont pas prévenus.

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
   fichiers compris. Un dossier **vivant mais sans aucune activité depuis
   36 mois** (`RETENTION_MONTHS`) est également effacé : sans cette règle, un
   dossier « validé » conserverait un passeport indéfiniment. Un préavis part
   aux comptes `admin` 30 jours avant, pour permettre une exception — toute
   intervention sur le dossier repousse l'échéance.
7. L'inscription publique est **désactivée**. Les comptes se créent en console.
8. L'accès au back-office exige un **e-mail vérifié et un rôle**.
9. Les documents sont **toujours servis en pièce jointe**, avec
   `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'`
   et un type générique. Aucune prévisualisation n'existe dans le back-office :
   un PDF légitimement formé peut porter du code, il ne doit jamais être rendu
   par le navigateur.
10. Les pièces déposées passent par **ClamAV** (`DOCUMENT_SCAN_ENABLED`).
    L'analyse est **dégradable** : antivirus absent ou en panne ne bloque aucun
    dépôt. Seul un fichier formellement reconnu infecté est retenu — son
    téléchargement est refusé, le dossier passe en « incomplet » et les comptes
    `admin` sont alertés. Voir `DEPLOIEMENT.md` pour l'installation.

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

`ln:purge-applications` accepte `--dry-run` : il annonce alors les deux règles
et liste les dossiers arrivant à échéance, sans rien effacer.

### Consentement et droit à l'effacement

Le dépôt exige une **case à cocher obligatoire**, vérifiée côté serveur et pas
seulement par le navigateur. Sont enregistrés la date du consentement et la
**version de la politique acceptée** (`PRIVACY_VERSION`) : sans elle, on ne
saurait pas à quoi le candidat a consenti.

Un administrateur peut **effacer définitivement** un dossier depuis sa fiche.
La confirmation impose de recopier la référence — sur une action irréversible
qui détruit des pièces d'identité, un simple « Oui » ne suffit pas. Seule
subsiste une ligne du journal d'activité attestant l'opération et son auteur,
sans aucune donnée du candidat.

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

Les archives sont **chiffrées en AES-256** avec `BACKUP_ARCHIVE_PASSWORD`.

> ⚠ Sans cette variable, `laravel-backup` produit une archive valide et
> **parfaitement lisible**, sans aucun avertissement. Le contrôle de santé
> quotidien rattrape ce silence.

Seuls les échecs déclenchent une notification, pour que les vraies alertes ne se
noient pas dans le bruit. Mais une sauvegarde qui **ne se lance plus** n'échoue
pas : elle n'existe pas. `backup:monitor`, exécuté chaque matin à 08h00, vérifie
donc trois choses :

| Contrôle | Ce qu'il rattrape |
|---|---|
| Âge maximal : 25 h | Cron cassé, disque plein, identifiants distants expirés |
| Taille minimale | Dump de base vide ou tronqué, `mysqldump` devenu inaccessible |
| Chiffrement effectif | Mot de passe absent : archives en clair sans avertissement |

```bash
php artisan backup:run     # sauvegarde manuelle
php artisan backup:list    # état des sauvegardes
php artisan backup:monitor # contrôle de santé
```

La procédure de **test de restauration** est décrite dans `DEPLOIEMENT.md`,
section 6. À exécuter avant la mise en production, puis chaque trimestre.

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
