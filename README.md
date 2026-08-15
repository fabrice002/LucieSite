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

## Sommaire

1. [Stack](#1-stack)
2. [Installation en développement](#2-installation-en-développement)
3. [Architecture](#3-architecture)
4. [Sécurité](#4-sécurité)
5. [Upload résilient](#5-upload-résilient)
6. [Tâches planifiées](#6-tâches-planifiées)
7. [Sauvegardes](#7-sauvegardes)
8. [Qualité](#8-qualité)
9. [Déploiement](#9-déploiement)
10. [Dépannage](#10-dépannage)

## Les cinq décisions à connaître avant de toucher au code

| Décision | Pourquoi |
|---|---|
| L'e-mail de suivi n'est qu'une **alerte** | Une boîte e-mail peut être partagée ou compromise. Le message du cabinet ne se lit que sur la plateforme, après vérification référence + e-mail. |
| Les documents vont sur le **disque privé**, servis en pièce jointe | Un PDF légitimement formé peut porter du code. Aucune prévisualisation n'existe nulle part. |
| Les limiteurs de débit sont **nommés** | La forme anonyme `throttle:5,1` partage une clé entre toutes les routes. Voir §4. |
| Aucun **texte visible** n'est écrit en dur dans une vue publique | Tout passe par `content()` pour rester modifiable par la cliente. |
| Les notifications sont **en file d'attente** | Sans worker, aucun e-mail ne part. C'est la première cause de « ça ne marche pas ». |

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
| Archives ZIP | maennchen/zipstream-php (diffusion en flux) |
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
| `/services/{slug}` | Fiche d'un service — 404 tant qu'il n'est pas publié |
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
| Contenu du site › Services | Fiches de programmes, image, publication, ordre | admin |
| Contenu du site › Questions fréquentes | Thèmes et questions, publication, ordre | admin |
| Contenu du site › Blocs de page | Composition des pages, bloc par bloc | admin |
| Contenu du site › Équipe | Membres présentés sur « À propos » | admin |
| Contenu du site › Apparence | Couleurs, logo, police, thème sombre | admin |
| Comptes et rôles | Création de comptes, attribution des rôles | admin |
| Mon profil / Sécurité | Nom, e-mail, mot de passe, double authentification | admin, agent |

**Rôles :**

- `admin` — tous les droits, y compris les suppressions, les textes du site et
  la gestion des comptes.
- `agent` — consultation des dossiers, changement de statut, notes internes.
  Ni suppression, ni textes du site, ni gestion des comptes.

### Apparence : logo, couleurs et police

Tout se règle depuis le back-office, **Contenu du site › Apparence**, sans
développeur et sans recompilation. Une modification est visible immédiatement
sur le site public.

| Réglage | Effet |
|---|---|
| Palette | Remplit les quatre couleurs d'un coup. Cinq propositions, toutes conformes au contraste minimum |
| Couleur principale | Boutons, liens, aplats. Les nuances de survol, de fond doux et de filet en sont **calculées** |
| Texte sur la couleur principale | La couleur du texte posé sur ces aplats |
| Couleur secondaire, accent | Éléments d'appui et mises en avant ponctuelles |
| Logo, logo pour fond sombre, icône d'onglet | Téléversés. Sans logo, le monogramme « LN » est tracé et suit le thème |
| Police | Quatre familles auto-hébergées ; **une seule est téléchargée par visiteur** |
| Thème sombre | Désactivé, le site reste clair et le bouton de bascule disparaît |

**Garde-fou de contraste.** À l'enregistrement, le rapport de contraste WCAG
entre la couleur principale et son texte est calculé. Sous 4,5:1, un
avertissement indique le ratio obtenu et le minimum requis. L'enregistrement
reste possible : c'est son site, elle décide en connaissance de cause.

**Icônes du navigateur.** Le bouton « Régénérer les icônes » rejoue
`php artisan ln:generate-icons`, qui redessine `favicon.svg`, `favicon.ico` et
`apple-touch-icon.png` à partir de la couleur principale et de son texte.
L'onglet suit donc la palette choisie.

> Les navigateurs conservent les favicons très longtemps : videz le cache du
> vôtre, sinon l'ancienne icône semblera persister.

**Comment les couleurs sont appliquées.** Par des **variables CSS** injectées
dans le `<head>`, après la feuille de style, qui surchargent les jetons de
`resources/css/public.css`. Aucune classe Tailwind n'est construite
dynamiquement — `bg-{{ $couleur }}` n'existerait jamais dans le CSS compilé,
puisque le compilateur ne voit pas ces classes au build.

**Replis.** `config/brand.php` conserve les valeurs livrées, sous la clé
`apparence`. Table vide, valeur effacée ou couleur mal formée : le site reprend
son apparence d'origine plutôt que de produire une feuille cassée. La variable
`BRAND_LOGO` reste honorée pour les installations qui s'en servaient.

**Ajouter une police** demande une intervention technique, car les fichiers sont
téléchargés au build : ajouter la famille dans `vite.config.js` **et** dans
`brand.polices`, avec le même alias (le nom en minuscules et tirets), puis
`npm run build`.

### Modèle de données

| Table | Rôle | Points d'attention |
|---|---|---|
| `applications` | Un dossier déposé | `reference` unique (`LN-2026-00147`), l'`id` n'est jamais exposé. `SoftDeletes`. `internal_notes` **strictement privé**. `consented_at` + `privacy_version` |
| `documents` | Une ligne **par fichier** | `path` en UUID sur le disque privé, `original_name` pour l'affichage seul. `scan_status` renseigné par ClamAV |
| `application_updates` | Messages adressés au candidat | Lisibles sur la page de suivi. `applications.status` reste la source de vérité du statut |
| `testimonials` | Témoignages | Créés uniquement depuis le back-office. `photo_path` sur le disque **public** — le seul fichier qui y va. `author_programme` affiché avec le pays : un témoignage sans contexte ne rassure personne |
| `site_contents` | Tous les textes publics | JSON à plat, unique sur `(key, locale)`. Ajouter une langue = insérer des lignes |
| `services` | Une fiche de programme | `slug` unique et exposé à la place de l'`id`. Non publié = absent des listes, du plan du site, et 404 sur sa fiche. `included` / `excluded` / `price_note` : le périmètre affiché sur la fiche |
| `faq_categories` / `faqs` | Thèmes et questions | Un thème dépublié masque ses questions ; un thème sans question publiée n'apparaît pas |
| `page_sections` | Blocs empilables d'une page | `type` en **chaîne**, pas en enum : un type retiré du code est ignoré, jamais une page cassée. `data` en JSON, sa forme dépend du type |
| `team_members` | Membres présentés sur « À propos » | La section entière disparaît si personne n'est publié |
| `site_settings` | Réglages d'apparence | Une ligne par réglage : ajouter une couleur n'impose aucune migration. Vide = apparence livrée |
| `users` | Comptes du cabinet | Aucun compte candidat. `must_change_password` pour les mots de passe provisoires |
| `activity_log` | Journal | Dépôts, changements de statut, téléchargements, effacements |

### Conception des pages publiques

La mise en page suit les conventions du secteur, qui ne sont pas décoratives :
elles répondent à l'inquiétude d'un candidat qui confie un projet de vie et de
l'argent à un cabinet qu'il ne connaît pas.

| Élément | Où | Règle |
|---|---|---|
| Appel à l'action dominant | En-tête, hero, bas de chaque page | Un seul, répété : « Déposer mon dossier ». Le second, « Poser une question », reste secondaire |
| Bande de réassurance | Sous le hero | Bloc `reassurance`. **Livrée vide** — invisible tant qu'aucune donnée réelle n'est saisie |
| Notre processus | Accueil | `<x-process-steps>` : le nombre d'étapes découle des clés `etape_N_titre`, rien n'est figé |
| Services en cartes | Accueil et page Services | Chaque carte mène à sa page détaillée |
| Périmètre de la prestation | Fiche d'un service | Ce qui est compris **et ce qui ne l'est pas** |
| Témoignages | Accueil et page dédiée | Prénom, pays et programme obtenu, photo si la personne l'a fournie |
| Coordonnées complètes | Pied de page | Adresse, téléphone, WhatsApp, e-mail, horaires. L'adresse physique est un signal de légitimité déterminant |
| Statut professionnel | À propos et pied de page | À quel titre le cabinet intervient. Masqué tant qu'il n'est pas rédigé |

#### Ce que le site ne fait jamais

Ce secteur attire la fraude, et les candidats d'Afrique centrale en sont une
cible fréquente. Ces règles protègent les candidats autant que la réputation du
cabinet — un test les vérifie (`tests/Feature/PublicDesignTest.php`).

- **Aucune promesse de résultat** : ni « visa garanti », ni taux de réussite, ni
  compte à rebours artificiel.
- **Aucune statistique inventée** : les blocs `chiffres` et la bande de
  réassurance sont livrés **vides**, pas avec des exemples.
- **Aucun témoignage fictif publié** : les exemples du seeder portent la mention
  « exemple — à remplacer » et sont non publiés.
- **Aucun agrément supposé** : le statut professionnel et son numéro restent des
  placeholders tant que la cliente ne les a pas renseignés.
- **Aucune photo de banque d'images** : `<x-media-slot>` affiche un aplat de la
  charte tant qu'aucune photo authentique n'est fournie. Un visuel générique est
  le premier signal de défiance.

#### Placeholder ou texte réel

`content()` rend le texte tel quel, placeholder compris — c'est voulu pour les
textes éditoriaux, qui doivent se voir tant qu'ils ne sont pas écrits.

Partout où un contenu manquant doit faire **disparaître** l'élément, utilisez
`content_filled()`, qui renvoie `null` sur un texte vide ou marqué
`[À COMPLÉTER PAR LA CLIENTE]` :

```blade
@if ($telephone = content_filled('global.footer_telephone'))
    <a href="tel:{{ preg_replace('/[^\d+]/', '', $telephone) }}">{{ $telephone }}</a>
@endif
```

Sans cette précaution, un placeholder deviendrait un numéro de téléphone
cliquable ou un chiffre présenté comme un fait.

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

### Contenu structuré : services, FAQ, blocs de page, équipe

Au-delà des textes, quatre contenus se gèrent depuis **Contenu du site** sans
toucher au code. Aucun plafond n'est codé : la cliente ajoute autant de
services, de questions, de blocs ou de membres qu'elle veut.

Une page publique se compose en empilant des **blocs**. Neuf types existent
(`App\Enums\SectionType`) : bannière, texte + image, cartes, étapes, chiffres,
galerie, citation, appel à l'action, logos. Le formulaire du back-office change
selon le type choisi, l'administratrice ne voit jamais de JSON.

```blade
<x-page-sections page="accueil" />
```

Ajouter un type de bloc tient en trois gestes :

1. une valeur dans `App\Enums\SectionType`
2. la liste de ses champs dans `App\Filament\Forms\SectionBlocks::pour()`
3. un partial `resources/views/sections/<valeur>.blade.php`

Le champ `type` est stocké en **chaîne, jamais casté en enum** : un type retiré
du code doit rendre le bloc invisible, pas faire tomber la page. La lecture
passe par `PageSection::sectionType()`, qui renvoie `null` si le type est
inconnu.

#### Ce qui transite par le cache

Les listes publiées sont mises en cache et invalidées à chaque écriture
(`App\Models\Concerns\CachesPublicContent`). **Seuls des tableaux d'attributs
bruts y entrent, jamais des objets.** Laravel fixe `cache.serializable_classes`
à `false` : aucune classe PHP n'est désérialisée depuis le cache, ce qui ferme
les chaînes de gadgets si l'`APP_KEY` fuite.

Un modèle Eloquent mis en cache reviendrait donc en `__PHP_Incomplete_Class` au
deuxième appel — page blanche en 500, invisible en développement tant que le
cache reste froid. Le motif à suivre est toujours le même : mettre en cache
`getAttributes()`, réhydrater avec `hydrate()` à la lecture, et reconstruire les
relations à la main (`setRelation`), qu'`hydrate()` ne restitue pas.

> La suite de tests tourne par défaut sur le store `array`, qui ne sérialise
> rien et ne verrait pas ce défaut. `tests/Feature/PublicContentCacheTest.php`
> force un store aux mêmes règles que la production — laissez-le en place.

Le contenu de départ vient de `ContentSeeder`, qui ne réécrit jamais l'existant.
Les blocs et les services y sont livrés **non publiés** : ils portent des
placeholders `[À COMPLÉTER PAR LA CLIENTE]`, qui n'ont rien à faire en ligne.

```bash
php artisan db:seed --class=ContentSeeder
```

### Logique métier

Les contrôleurs orchestrent, la logique vit dans `app/Actions` :

| Classe | Rôle |
|---|---|
| `SubmitApplication` | Enregistre un dossier et ses documents, en transaction |
| `GenerateApplicationReference` | Génère `LN-2026-00147` |
| `BuildApplicationArchive` | Diffuse les documents en ZIP, en flux |
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

| Limiteur | Plafond | Clé |
|---|---|---|
| `depot` | 5 / minute | IP |
| `suivi` | 10 / minute | IP **+ référence saisie** |
| `suivi` (2ᵉ limite) | 60 / minute | IP |
| `televersement` | 300 / minute | IP |

**Pourquoi la clé du suivi combine l'IP et la référence.** Au Cameroun, une
grande part des abonnés mobiles partagent une même adresse IP publique via le
CGNAT, et les cybercafés davantage encore. Un plafond par IP seule bloquerait
des candidats parfaitement légitimes qui n'ont fait que consulter leur dossier.
Avec cette clé, deux candidats derrière la même IP ne se gênent plus ; la
seconde limite, purement par IP, continue de freiner une énumération massive.

Si des blocages sont constatés sur le dépôt, la même logique s'applique :
combiner l'IP et l'adresse e-mail. Le commentaire dans `AppServiceProvider`
donne la ligne exacte.

> ⚠️ N'utilisez **jamais** la forme anonyme `throttle:5,1` sur ce projet. Elle
> partage une seule clé `domaine|IP` entre **toutes** les routes : les requêtes
> d'envoi par tranches épuiseraient alors le quota du formulaire de dépôt, et le
> candidat recevrait un 429 au moment de valider son dossier.

> ⚠️ Renseignez `TRUSTED_PROXIES` si le site est derrière un reverse proxy.
> Sinon Laravel voit l'adresse du proxy : **toutes** les requêtes partagent la
> même IP et les limiteurs bloquent tout le monde.

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

### Téléchargement groupé

« Télécharger tous les documents » **diffuse l'archive en flux** : rien n'est
construit en mémoire, rien n'est écrit dans un fichier temporaire. Chaque pièce
est lue depuis le disque et poussée vers le navigateur au fil de l'eau.

Mesuré sur le pire cas du cahier des charges — huit scans de 10 Mo, contenu
incompressible : **80 Mo diffusés sans croissance mémoire mesurable**. Le
premier appel alloue le tas PHP habituel ; les suivants ne consomment rien de
plus, quel que soit le volume.

> Derrière un frontal, `X-Accel-Buffering: no` est envoyé pour empêcher Nginx de
> mettre la réponse en tampon — ce qui annulerait tout l'intérêt du flux.

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

---

## 10. Dépannage

Les pannes ci-dessous ont toutes été rencontrées sur ce projet. Elles ont en
commun de **ne produire aucune erreur visible** : tout semble fonctionner.

### Aucun e-mail n'arrive

**Cause la plus fréquente : le worker ne tourne pas.** Les notifications sont
mises en file d'attente ; sans worker, elles y restent indéfiniment.

```bash
php artisan queue:work          # à laisser tourner
php artisan queue:failed        # erreurs SMTP éventuelles
php artisan ln:test-mail vous@exemple.com   # envoi immédiat, hors file
```

`ln:test-mail` court-circuite la file : si le message part, la configuration
SMTP est bonne et le problème vient du worker. La commande traduit aussi les
erreurs SMTP courantes en action concrète.

Un **bandeau rouge** apparaît sur le tableau de bord des comptes `admin` dès
qu'un e-mail attend depuis plus de dix minutes.

> Après avoir modifié `.env` : `php artisan config:clear`, **puis redémarrez le
> worker**. Il garde l'application en mémoire et continuerait sinon d'utiliser
> l'ancienne configuration.

### Les photos des témoignages ne s'affichent pas

Le lien `public/storage` n'existe pas :

```bash
php artisan storage:link
```

Si les photos apparaissent en local mais pas depuis un téléphone, vérifiez que
l'URL du disque public est bien **relative** dans `config/filesystems.php`.
Construite depuis `APP_URL`, elle casse dès que le site est consulté sur un
autre hôte ou un autre port.

### Un fichier PDF valide est refusé par le formulaire

L'attribut `accept` des champs fichier doit contenir des **types MIME**, jamais
des extensions. FilePond traduit cet attribut en `acceptedFileTypes` et les
attributs de l'élément **priment** sur les options passées au code : avec
`.pdf`, il compare `".pdf"` au type réel `"application/pdf"` et refuse tout.

Un repli sur l'extension est en place pour Android, qui renvoie souvent un type
MIME vide selon l'application depuis laquelle le fichier est choisi.

### Un 429 apparaît alors que le trafic est faible

Deux causes possibles :

1. **Un `throttle:5,1` anonyme** a été introduit quelque part. Cette forme
   partage une seule clé `domaine|IP` entre **toutes** les routes. Utilisez
   toujours un limiteur nommé.
2. **`TRUSTED_PROXIES` n'est pas renseigné** derrière un reverse proxy. Laravel
   voit alors l'adresse du proxy : toutes les requêtes partagent la même IP.

```bash
php artisan tinker --execute="echo request()->ip();"
```

### Les sauvegardes de la base sont vides

`mysqldump` n'est pas dans le `PATH`. Renseignez `DB_DUMP_BINARY_PATH` avec le
dossier qui le contient. Le contrôle de taille minimale de `backup:monitor`
détecte ce cas.

### Toutes les pièces déposées passent en « infecté »

Vérifiez que `clamdscan` est réellement installé. Un binaire absent fait sortir
le shell avec le code 1 — **celui-là même que ClamAV réserve aux fichiers
infectés**. Le code se prémunit contre ce cas, mais un ClamAV mal configuré
peut produire d'autres faux positifs :

```bash
which clamdscan && clamdscan --version
```

Pour désactiver temporairement : `DOCUMENT_SCAN_ENABLED=false`.

### Le téléchargement d'un document renvoie 403

Trois causes, dans l'ordre de fréquence :

1. le compte n'a **ni `admin` ni `agent`** ;
2. le fichier est marqué **infecté** — c'est voulu ;
3. l'e-mail du compte n'est **pas vérifié**.

### Les textes modifiés dans le back-office n'apparaissent pas

Le cache aurait dû être invalidé par `SiteContentObserver`. En dernier recours :

```bash
php artisan cache:clear
```

### Après un déploiement, le site sert l'ancien code

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart   # indispensable : les workers gardent l'ancien code
```
