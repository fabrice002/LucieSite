# Projet — Site web « LN Immigration »

## 1. Contexte

LN Immigration est un cabinet camerounais qui accompagne des candidats dans leurs
démarches d'immigration vers le Canada. Le site a deux fonctions :

1. **Vitrine** : présenter le cabinet et ses services.
2. **Dépôt de dossiers** : permettre à un candidat de déposer en ligne son CV au
   format canadien, ses résultats TCF/TEF, son passeport et ses diplômes, et à
   l'administratrice de consulter, filtrer et traiter ces dossiers depuis un
   back-office.

Le site est **entièrement en français**. Les utilisateurs finaux se connectent
majoritairement depuis un téléphone Android, en 3G, avec des scans photographiés
de 5 à 10 Mo. Cette contrainte prime sur tout le reste : un formulaire qui se
perd pendant l'upload rend le site inutilisable.

## 2. Stack imposée

- **Laravel**, dernière version stable (vérifie la version courante, ne suppose pas)
- **PHP 8.3+**
- **MySQL 8** (ou MariaDB 10.11+)
- **Filament**, dernière version stable, pour tout le back-office
- **Blade + Tailwind CSS** pour les pages publiques (pas de SPA, pas d'Inertia,
  pas de React — le site doit rester léger et indexable)
- **Alpine.js** uniquement si une interaction le justifie
- **FilePond** (ou `pion/laravel-chunk-upload`) pour l'upload résilient
- Queues via **database driver** en développement, **Redis** en production

Packages Composer attendus :
`filament/filament`, `spatie/laravel-activitylog`, `spatie/laravel-backup`,
`spatie/laravel-permission`, `laravel/pint`, `larastan/larastan`, `pestphp/pest`

N'ajoute aucune autre dépendance sans me demander d'abord.

## 3. Modèle de données

### `users`
Administrateurs uniquement. Aucun candidat ne possède de compte.
Champs standards Laravel + relation vers les rôles (spatie/laravel-permission).
Rôles : `admin` (tous droits) et `agent` (consultation et changement de statut,
pas de suppression).

### `applications`
| colonne | type | notes |
|---|---|---|# Projet — Site web « LN Immigration »

## 1. Contexte

LN Immigration est un cabinet camerounais qui accompagne des candidats dans leurs
démarches d'immigration vers le Canada. Le site a deux fonctions :

1. **Vitrine** : présenter le cabinet et ses services.
2. **Dépôt de dossiers** : permettre à un candidat de déposer en ligne son CV au
   format canadien, ses résultats TCF/TEF, son passeport et ses diplômes, et à
   l'administratrice de consulter, filtrer et traiter ces dossiers depuis un
   back-office.

Le site est **entièrement en français**. Les utilisateurs finaux se connectent
majoritairement depuis un téléphone Android, en 3G, avec des scans photographiés
de 5 à 10 Mo. Cette contrainte prime sur tout le reste : un formulaire qui se
perd pendant l'upload rend le site inutilisable.

## 2. Stack et existant

### 2.1 Le projet est déjà initialisé — ne repars pas de zéro

Un **starter kit Laravel est déjà installé et configuré** dans ce dépôt, avec de
l'authentification et des mécanismes de sécurité (vérification d'adresse e-mail,
limitation de tentatives, réinitialisation de mot de passe, etc.).

Ta toute première tâche est un **audit de l'existant**, avant d'écrire la moindre
ligne. Tu dois :

1. Lire `composer.json`, `package.json`, `config/auth.php`, `routes/*.php`,
   `app/Models/User.php`, `app/Http/Middleware/`, les migrations existantes et
   les vues déjà présentes.
2. Me produire un rapport court qui liste : le starter kit identifié et sa
   version, la stack front qu'il impose (Blade, Livewire, Inertia + React/Vue),
   les fonctionnalités d'authentification et de sécurité déjà en place, et les
   packages déjà installés.
3. Me dire explicitement quels points de ce document entrent en conflit avec
   l'existant, et attendre mon arbitrage.

**Tu ne réinstalles pas, ne remplaces pas et ne réécris pas ce qui existe déjà.**
Si le starter kit fournit la vérification d'e-mail, tu la réutilises. S'il
fournit un middleware de throttling, tu t'appuies dessus. Tout ce que tu ajoutes
doit suivre les conventions déjà présentes dans le code, pas les tiennes.

### 2.2 Contraintes techniques

- **Laravel**, version déjà installée — ne la change pas
- **PHP 8.3+**
- **MySQL 8** (ou MariaDB 10.11+)
- **Filament**, dernière version stable, pour tout le back-office
- **Front public** : la technologie est celle imposée par le starter kit. Si tu as
  le choix, privilégie Blade + Tailwind pour rester léger et indexable. N'introduis
  pas une seconde stack front en parallèle de celle déjà en place.
- **FilePond** (ou `pion/laravel-chunk-upload`) pour l'upload résilient
- Queues via **database driver** en développement, **Redis** en production

Packages Composer à ajouter **s'ils ne sont pas déjà présents** :
`filament/filament`, `spatie/laravel-activitylog`, `spatie/laravel-backup`,
`spatie/laravel-permission`, `laravel/pint`, `larastan/larastan`, `pestphp/pest`

N'ajoute aucune autre dépendance sans me demander d'abord.

## 3. Modèle de données

### `users`
Administrateurs uniquement. Aucun candidat ne possède de compte.
Champs standards Laravel + relation vers les rôles (spatie/laravel-permission).
Rôles : `admin` (tous droits) et `agent` (consultation et changement de statut,
pas de suppression).

### `applications`
| colonne | type | notes |
|---|---|---|
| id | bigint | |
| reference | string, unique | format `LN-2026-00147`, généré, jamais l'ID exposé |
| first_name, last_name | string | |
| email | string | |
| phone | string | format international, ex. +237... |
| country_of_residence | string | |
| target_program | string, nullable | ex. « Entrée Express », « PSTQ » |
| message | text, nullable | mot libre du candidat |
| status | enum | `nouveau`, `en_cours`, `incomplet`, `valide`, `rejete` — défaut `nouveau` |
| internal_notes | text, nullable | visible admin uniquement |
| ip_address | string, nullable | pour la traçabilité anti-abus |
| timestamps + softDeletes | | |

### `documents`
| colonne | type | notes |
|---|---|---|
| id | bigint | |
| application_id | foreignId, cascade on delete | |
| type | enum | `cv`, `tcf_tef`, `passeport`, `diplome`, `autre` |
| original_name | string | nom d'origine, pour l'affichage seulement |
| path | string | chemin sur le disque privé |
| mime_type | string | |
| size | unsignedBigInteger | octets |
| timestamps | | |

**Important** : une ligne par fichier. Pas de colonnes `cv_path`, `tcf_path`, etc.
Un dossier peut contenir plusieurs diplômes et plusieurs pièces « autre ».

### `testimonials`
| colonne | type | notes |
|---|---|---|
| id | bigint | |
| author_name | string | |
| author_country | string, nullable | |
| content | text | |
| photo_path | string, nullable | disque public, celui-ci seulement |
| video_url | string, nullable | lien YouTube/Vimeo |
| is_published | boolean | défaut `false` |
| sort_order | integer | défaut 0 |
| timestamps | | |

Les témoignages sont créés **exclusivement par l'administratrice** depuis le
back-office. Il n'y a pas de soumission publique de témoignages.

### `site_contents`
Stocke **tout le texte affiché sur les pages publiques**, pour qu'il soit
modifiable sans toucher au code.

| colonne | type | notes |
|---|---|---|
| id | bigint | |
| key | string, unique | identifiant de la page ou du bloc, ex. `accueil`, `services`, `footer` |
| label | string | libellé lisible affiché dans le back-office, ex. « Page d'accueil » |
| content | json | l'ensemble des textes de ce bloc |
| timestamps | | |

La colonne `content` est un JSON à plat, en paires clé/valeur :

```json
{
  "hero_titre": "Votre projet d'immigration au Canada commence ici",
  "hero_sous_titre": "Un accompagnement complet, de l'évaluation au dépôt.",
  "hero_bouton": "Déposer mon dossier",
  "section_services_titre": "Nos services"
}
```

Les valeurs par défaut sont fournies par un **seeder** (`SiteContentSeeder`), qui
constitue la source de vérité initiale et permet de reconstruire le site sur une
base vierge. Le seeder utilise `updateOrCreate` sur la clé `key` : le relancer ne
doit jamais écraser une modification faite depuis le back-office.

Prévois un helper global lisible dans les vues, avec cache et repli :

```php
content('accueil.hero_titre', 'Titre par défaut')
```

Le cache est invalidé automatiquement à chaque sauvegarde d'un `SiteContent`
(via un observer ou l'événement `saved` du modèle).

### `activity_log`
Fournie par `spatie/laravel-activitylog`. Journalise création, changement de
statut, suppression et téléchargement de document, avec l'utilisateur auteur.

## 4. Fonctionnalités — côté public

Pages Blade : Accueil, Services, À propos, Témoignages, FAQ, Contact,
Déposer mon dossier, Suivre mon dossier, Mentions légales,
Politique de confidentialité.

### Formulaire de dépôt (`/deposer-mon-dossier`)
- Étape unique, pas de wizard multi-pages
- Champs identité + zones d'upload par type de document
- CV et TCF/TEF obligatoires ; passeport, diplômes et autres facultatifs
- Upload en chunks avec barre de progression et reprise après coupure réseau
- Compression côté client des images avant envoi (`browser-image-compression`)
- Les champs texte déjà saisis sont conservés en `localStorage` et restaurés si
  la page est rechargée ou si l'upload échoue
- À la validation : génération de la référence, enregistrement, redirection vers
  une page de confirmation affichant clairement la référence de suivi
- Notification e-mail au candidat **et** à l'administratrice, envoyées en queue

### Suivi de dossier (`/suivre-mon-dossier`)
Un champ référence + un champ e-mail. Si les deux correspondent, affiche
uniquement le statut en clair et la date de dernière mise à jour. Aucun document,
aucune note interne. Rate limit strict : 5 tentatives par minute par IP.

## 5. Fonctionnalités — back-office Filament

Accessible sur `/admin`, authentification obligatoire.

**ApplicationResource**
- Table : référence, nom complet, e-mail, téléphone, statut (badge coloré),
  nombre de documents, date de dépôt
- Filtres : par statut, par plage de dates, par pays
- Recherche : nom, e-mail, référence
- Vue détail : informations candidat, liste des documents avec bouton de
  téléchargement, champ notes internes, sélecteur de statut
- Action groupée : changement de statut sur plusieurs dossiers
- Action : télécharger tous les documents d'un dossier en une archive ZIP
- Suppression réservée au rôle `admin`

**TestimonialResource**
CRUD complet, upload de photo, bascule « publié », réordonnancement.

**SiteContentResource — édition des textes du site**
- Liste des blocs par `label`, sans possibilité de créer ni de supprimer un bloc :
  seule l'édition est autorisée, car les clés sont référencées dans les vues
- Le formulaire d'édition est **généré dynamiquement** à partir des clés présentes
  dans le JSON du bloc : un champ par clé, avec le nom de la clé comme libellé.
  L'administratrice ne voit jamais de JSON brut.
- Champs courts en `TextInput`, champs longs en `Textarea`, et `RichEditor` pour
  les clés dont le nom se termine par `_html`
- Une modification est immédiatement visible sur le site public (cache invalidé)
- Réservé au rôle `admin`

**Tableau de bord**
Widgets simples : nombre de dossiers par statut, dossiers reçus ce mois,
5 derniers dossiers reçus.

## 6. Sécurité — non négociable

Ces points ne sont pas des suggestions. Le site héberge des scans de passeports.

1. **Les documents des candidats vont sur le disque privé** (`storage/app/private`),
   jamais sur `storage/app/public`, jamais accessibles par URL directe. Seule la
   photo des témoignages va sur le disque public.
2. Le téléchargement passe par une route authentifiée protégée par une **Policy**,
   qui renvoie `Storage::disk('local')->download(...)`.
3. Validation dans des **Form Requests** : `['required','file','mimes:pdf,jpg,jpeg,png','max:10240']`.
   Utilise `mimes` (qui inspecte le contenu réel), pas `extensions`.
4. Nom de fichier stocké généré en **UUID**. Le nom d'origine ne sert qu'à
   l'affichage et n'est jamais utilisé pour écrire sur le disque.
5. `throttle:5,1` sur la soumission du formulaire, plus un champ honeypot.
6. `SoftDeletes` sur `applications`, et une commande artisan planifiée qui purge
   définitivement les dossiers supprimés depuis plus de 90 jours, fichiers compris.
7. `spatie/laravel-backup` configuré vers un stockage distant, planifié quotidiennement.
8. `APP_DEBUG=false` et `APP_ENV=production` dans `.env.example` de production.
9. Aucun secret dans le dépôt. `.env.example` complet et à jour.

## 7. Conventions de code

- Validation exclusivement en **Form Requests**
- Logique métier dans des classes **Action** sous `app/Actions`
  (`SubmitApplication`, `GenerateApplicationReference`, `PurgeExpiredApplications`).
  Les contrôleurs orchestrent et ne dépassent pas une quinzaine de lignes.
- Les notifications sont des **queued jobs** (`ShouldQueue`). Le candidat ne doit
  jamais attendre l'envoi SMTP.
- Enums PHP natifs pour `status` et `type`, pas de chaînes libres
- Nommage : classes en anglais, contenu affiché et messages de validation en français
- **Aucun texte en dur dans les vues publiques.** Tout titre, paragraphe, libellé
  de bouton ou intitulé de section passe par le helper `content()` et la table
  `site_contents`. Une chaîne de texte visible écrite directement dans un fichier
  Blade est une erreur à corriger.
  Exception : les libellés du back-office Filament, qui restent dans le code.
- Réutilise systématiquement ce que le starter kit fournit — middlewares,
  vérification d'e-mail, throttling, layouts, composants de formulaire — plutôt
  que d'écrire un équivalent
- `php artisan lang:publish` puis traduction française des messages de validation
- Code conforme **PSR-12**, vérifié par Laravel Pint
- **Larastan niveau 5** doit passer sans erreur
- Chaque migration est réversible (`down()` écrite, pas vide)

## 8. Tests attendus (Pest)

Quatre tests suffisent, mais ils doivent passer :

1. Une soumission valide crée l'`application`, enregistre les `documents` sur le
   disque privé (avec `Storage::fake`) et déclenche les notifications (avec
   `Notification::fake`)
2. Une soumission avec un fichier `.exe` renommé en `.pdf` est rejetée
3. Un utilisateur non authentifié accédant à la route de téléchargement d'un
   document reçoit une 403 ou une redirection, jamais le fichier
4. La recherche par référence + e-mail sur la page de suivi renvoie le statut,
   et une référence inconnue ne révèle rien
5. La modification d'un `SiteContent` invalide le cache et le nouveau texte
   apparaît sur la page publique concernée

## 9. Ordre de réalisation — arrête-toi à chaque palier

Réalise les phases dans cet ordre. **À la fin de chaque phase, arrête-toi,
résume ce que tu as fait et attends ma validation avant de continuer.**

- **Phase 0** — Audit de l'existant décrit en section 2.1. Tu produis le rapport
  et la liste des conflits. Tu n'écris aucun code dans cette phase.
- **Phase 1** — Complément d'outillage manquant uniquement (Pint, Larastan, Pest,
  `.env.example`). Migrations et modèles des six tables, avec les enums, les
  relations et les factories. Rien d'autre.
- **Phase 2** — Le parcours de dépôt côté serveur : Form Request, Action
  `SubmitApplication`, contrôleur, stockage privé, génération de référence,
  notifications en queue. Plus les quatre tests Pest.
- **Phase 3** — Le formulaire Blade avec FilePond, la compression d'images, la
  sauvegarde `localStorage`, la page de confirmation et la page de suivi.
- **Phase 4** — Filament : panel, ressources Application, Testimonial et
  SiteContent, policies, rôles, widgets, export ZIP.
- **Phase 5** — Le système de contenus éditables (helper `content()`, cache,
  observer, `SiteContentSeeder`), puis les pages vitrines (Accueil, Services,
  À propos, Témoignages, FAQ, Contact, mentions légales, politique de
  confidentialité), responsive, avec balises meta et sitemap. Tous les textes
  passent par `content()`.
- **Phase 6** — Commande de purge planifiée, configuration de `laravel-backup`,
  README d'installation et de déploiement, checklist de mise en production
  (valeurs `php.ini`, `client_max_body_size`, worker de queue, cron du scheduler).

## 10. Ce que tu ne dois pas faire

- Ne réinstalle pas, ne remplace pas et ne réécris pas le starter kit existant,
  en particulier l'authentification et la vérification d'e-mail
- N'écris pas de texte en dur dans une vue publique
- Ne crée pas de système de comptes pour les candidats
- N'écris pas un back-office à la main : tout passe par Filament
- Faire un Readme.MD detaillant le projet
- Ne stocke aucun document de candidat sur le disque `public`
- N'ajoute pas de dépendance hors de la liste de la section 2 sans me consulter
- Ne génère pas de contenu textuel définitif pour les pages vitrines : mets des
  placeholders clairs marqués `[À COMPLÉTER PAR LA CLIENTE]`
- N'invente pas de tarifs, de témoignages ou de statistiques de réussite
- Ne passe pas à la phase suivante sans ma validation explicite
| id | bigint | |
| reference | string, unique | format `LN-2026-00147`, généré, jamais l'ID exposé |
| first_name, last_name | string | |
| email | string | |
| phone | string | format international, ex. +237... |
| country_of_residence | string | |
| target_program | string, nullable | ex. « Entrée Express », « PSTQ » |
| message | text, nullable | mot libre du candidat |
| status | enum | `nouveau`, `en_cours`, `incomplet`, `valide`, `rejete` — défaut `nouveau` |
| internal_notes | text, nullable | visible admin uniquement |
| ip_address | string, nullable | pour la traçabilité anti-abus |
| timestamps + softDeletes | | |

### `documents`
| colonne | type | notes |
|---|---|---|
| id | bigint | |
| application_id | foreignId, cascade on delete | |
| type | enum | `cv`, `tcf_tef`, `passeport`, `diplome`, `autre` |
| original_name | string | nom d'origine, pour l'affichage seulement |
| path | string | chemin sur le disque privé |
| mime_type | string | |
| size | unsignedBigInteger | octets |
| timestamps | | |

**Important** : une ligne par fichier. Pas de colonnes `cv_path`, `tcf_path`, etc.
Un dossier peut contenir plusieurs diplômes et plusieurs pièces « autre ».

### `testimonials`
| colonne | type | notes |
|---|---|---|
| id | bigint | |
| author_name | string | |
| author_country | string, nullable | |
| content | text | |
| photo_path | string, nullable | disque public, celui-ci seulement |
| video_url | string, nullable | lien YouTube/Vimeo |
| is_published | boolean | défaut `false` |
| sort_order | integer | défaut 0 |
| timestamps | | |

Les témoignages sont créés **exclusivement par l'administratrice** depuis le
back-office. Il n'y a pas de soumission publique de témoignages.

### `activity_log`
Fournie par `spatie/laravel-activitylog`. Journalise création, changement de
statut, suppression et téléchargement de document, avec l'utilisateur auteur.

## 4. Fonctionnalités — côté public

Pages Blade : Accueil, Services, À propos, Témoignages, FAQ, Contact,
Déposer mon dossier, Suivre mon dossier, Mentions légales,
Politique de confidentialité.

### Formulaire de dépôt (`/deposer-mon-dossier`)
- Étape unique, pas de wizard multi-pages
- Champs identité + zones d'upload par type de document
- CV et TCF/TEF obligatoires ; passeport, diplômes et autres facultatifs
- Upload en chunks avec barre de progression et reprise après coupure réseau
- Compression côté client des images avant envoi (`browser-image-compression`)
- Les champs texte déjà saisis sont conservés en `localStorage` et restaurés si
  la page est rechargée ou si l'upload échoue
- À la validation : génération de la référence, enregistrement, redirection vers
  une page de confirmation affichant clairement la référence de suivi
- Notification e-mail au candidat **et** à l'administratrice, envoyées en queue

### Suivi de dossier (`/suivre-mon-dossier`)
Un champ référence + un champ e-mail. Si les deux correspondent, affiche
uniquement le statut en clair et la date de dernière mise à jour. Aucun document,
aucune note interne. Rate limit strict : 5 tentatives par minute par IP.

## 5. Fonctionnalités — back-office Filament

Accessible sur `/admin`, authentification obligatoire.

**ApplicationResource**
- Table : référence, nom complet, e-mail, téléphone, statut (badge coloré),
  nombre de documents, date de dépôt
- Filtres : par statut, par plage de dates, par pays
- Recherche : nom, e-mail, référence
- Vue détail : informations candidat, liste des documents avec bouton de
  téléchargement, champ notes internes, sélecteur de statut
- Action groupée : changement de statut sur plusieurs dossiers
- Action : télécharger tous les documents d'un dossier en une archive ZIP
- Suppression réservée au rôle `admin`

**TestimonialResource**
CRUD complet, upload de photo, bascule « publié », réordonnancement.

**Tableau de bord**
Widgets simples : nombre de dossiers par statut, dossiers reçus ce mois,
5 derniers dossiers reçus.

## 6. Sécurité — non négociable

Ces points ne sont pas des suggestions. Le site héberge des scans de passeports.

1. **Les documents des candidats vont sur le disque privé** (`storage/app/private`),
   jamais sur `storage/app/public`, jamais accessibles par URL directe. Seule la
   photo des témoignages va sur le disque public.
2. Le téléchargement passe par une route authentifiée protégée par une **Policy**,
   qui renvoie `Storage::disk('local')->download(...)`.
3. Validation dans des **Form Requests** : `['required','file','mimes:pdf,jpg,jpeg,png','max:10240']`.
   Utilise `mimes` (qui inspecte le contenu réel), pas `extensions`.
4. Nom de fichier stocké généré en **UUID**. Le nom d'origine ne sert qu'à
   l'affichage et n'est jamais utilisé pour écrire sur le disque.
5. `throttle:5,1` sur la soumission du formulaire, plus un champ honeypot.
6. `SoftDeletes` sur `applications`, et une commande artisan planifiée qui purge
   définitivement les dossiers supprimés depuis plus de 90 jours, fichiers compris.
7. `spatie/laravel-backup` configuré vers un stockage distant, planifié quotidiennement.
8. `APP_DEBUG=false` et `APP_ENV=production` dans `.env.example` de production.
9. Aucun secret dans le dépôt. `.env.example` complet et à jour.

## 7. Conventions de code

- Validation exclusivement en **Form Requests**
- Logique métier dans des classes **Action** sous `app/Actions`
  (`SubmitApplication`, `GenerateApplicationReference`, `PurgeExpiredApplications`).
  Les contrôleurs orchestrent et ne dépassent pas une quinzaine de lignes.
- Les notifications sont des **queued jobs** (`ShouldQueue`). Le candidat ne doit
  jamais attendre l'envoi SMTP.
- Enums PHP natifs pour `status` et `type`, pas de chaînes libres
- Nommage : classes en anglais, contenu affiché et messages de validation en français
- `php artisan lang:publish` puis traduction française des messages de validation
- Code conforme **PSR-12**, vérifié par Laravel Pint
- **Larastan niveau 5** doit passer sans erreur
- Chaque migration est réversible (`down()` écrite, pas vide)

## 8. Tests attendus (Pest)

Quatre tests suffisent, mais ils doivent passer :

1. Une soumission valide crée l'`application`, enregistre les `documents` sur le
   disque privé (avec `Storage::fake`) et déclenche les notifications (avec
   `Notification::fake`)
2. Une soumission avec un fichier `.exe` renommé en `.pdf` est rejetée
3. Un utilisateur non authentifié accédant à la route de téléchargement d'un
   document reçoit une 403 ou une redirection, jamais le fichier
4. La recherche par référence + e-mail sur la page de suivi renvoie le statut,
   et une référence inconnue ne révèle rien

## 9. Ordre de réalisation — arrête-toi à chaque palier

Réalise les phases dans cet ordre. **À la fin de chaque phase, arrête-toi,
résume ce que tu as fait et attends ma validation avant de continuer.**

- **Phase 1** — Installation Laravel, configuration `.env.example`, Tailwind,
  Pint, Larastan, Pest. Migrations et modèles des cinq tables, avec les enums,
  les relations et les factories. Rien d'autre.
- **Phase 2** — Le parcours de dépôt côté serveur : Form Request, Action
  `SubmitApplication`, contrôleur, stockage privé, génération de référence,
  notifications en queue. Plus les quatre tests Pest.
- **Phase 3** — Le formulaire Blade avec FilePond, la compression d'images, la
  sauvegarde `localStorage`, la page de confirmation et la page de suivi.
- **Phase 4** — Filament : panel, ressources Application et Testimonial,
  policies, rôles, widgets, export ZIP.
- **Phase 5** — Les pages vitrines (Accueil, Services, À propos, Témoignages,
  FAQ, Contact, mentions légales, politique de confidentialité), responsive,
  avec balises meta et sitemap.
- **Phase 6** — Commande de purge planifiée, configuration de `laravel-backup`,
  README d'installation et de déploiement, checklist de mise en production
  (valeurs `php.ini`, `client_max_body_size`, worker de queue, cron du scheduler).

## 10. Ce que tu ne dois pas faire

- Ne crée pas de système de comptes pour les candidats
- N'écris pas un back-office à la main : tout passe par Filament
- Ne stocke aucun document de candidat sur le disque `public`
- N'ajoute pas de dépendance hors de la liste de la section 2 sans me consulter
- Ne génère pas de contenu textuel définitif pour les pages vitrines : mets des
  placeholders clairs marqués `[À COMPLÉTER PAR LA CLIENTE]`
- N'invente pas de tarifs, de témoignages ou de statistiques de réussite
- Ne passe pas à la phase suivante sans ma validation explicite
