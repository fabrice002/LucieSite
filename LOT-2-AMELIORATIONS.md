# LN Immigration — Lot 2 : notifications de suivi et durcissement

## 0. À lire avant de commencer

L'application **existe déjà et fonctionne**. Sa stack, son architecture et ses
conventions sont décrites dans `README.md` — lis-le en entier avant d'écrire une
ligne de code, ainsi que `CLAUDE.md`, `DEPLOIEMENT.md`, `app/Actions/`,
`app/Providers/AppServiceProvider.php` et les ressources Filament existantes.

Ce document décrit **cinq lots de travail** à ajouter à l'existant. Il ne
remplace pas le `CLAUDE.md` initial, il le complète.

Règles impératives :

- Tu respectes les conventions déjà en place. Tu n'introduis ni nouveau style, ni
  nouvelle bibliothèque front, ni nouvelle façon de structurer la logique métier.
- Tu n'ajoutes aucune dépendance Composer ou npm sans me demander d'abord.
- **Tu traites un lot à la fois. À la fin de chaque lot, tu t'arrêtes, tu
  résumes ce que tu as fait, et tu attends ma validation.**
- `composer lint:check`, `composer types:check` (Larastan niveau 7) et
  `php artisan test` doivent passer à la fin de chaque lot.

Ordre de réalisation : **A, puis B, C, D, E**. Les lots B, C et D sont
bloquants pour la mise en production ; le lot A ne l'est pas.

---

## Lot A — Notifications de suivi de dossier

### A.1 Le besoin

Aujourd'hui l'administratrice change le statut d'un dossier, mais le candidat
n'en sait rien : il doit venir consulter la page de suivi au hasard.

On veut qu'elle puisse, depuis la fiche d'un dossier, **décider d'informer le
candidat en un clic**, en accompagnant éventuellement le changement de statut
d'un message écrit à son intention.

Le principe de circulation de l'information est le suivant, et il ne doit pas
être modifié :

- **L'e-mail est une simple alerte.** Il indique la référence et le nouveau
  statut, et invite à consulter la page de suivi. Il ne contient **jamais** le
  message rédigé par l'administratrice, ni de document, ni de note interne, ni
  de lien de connexion automatique.
- **Le message se lit uniquement sur la plateforme**, après que le candidat a
  saisi sa référence et son e-mail sur `/suivre-mon-dossier`.

C'est volontaire : une boîte e-mail peut être partagée, consultée sur un
téléphone prêté ou compromise. Le contenu sensible reste derrière la
vérification référence + e-mail.

### A.2 Nouvelle table `application_updates`

| colonne | type | notes |
|---|---|---|
| id | bigint | |
| application_id | foreignId, cascadeOnDelete | |
| user_id | foreignId, nullOnDelete | auteur de la mise à jour |
| status | enum `ApplicationStatus`, nullable | `null` = message sans changement de statut |
| public_message | text, nullable | visible par le candidat |
| email_sent | boolean, défaut `false` | |
| emailed_at | timestamp, nullable | |
| timestamps | | |

- `Application::updates()` — `hasMany`, triées du plus récent au plus ancien.
- `applications.status` **reste la source de vérité** du statut courant. Chaque
  mise à jour porteuse d'un statut met à jour la colonne `applications.status`
  dans la **même transaction**.
- La table ne remplace pas `internal_notes`, qui reste strictement privé.

### A.3 Action métier

Crée `app/Actions/NotifyApplicant.php`, dans le style des actions existantes.

Signature attendue :

```php
public function handle(
    Application $application,
    ?ApplicationStatus $status,
    ?string $publicMessage,
    bool $sendEmail,
    User $author,
): ApplicationUpdate
```

Comportement, dans une transaction :

1. Si `$status` est fourni et diffère du statut courant, met à jour
   `applications.status`.
2. Crée la ligne `application_updates`.
3. Journalise l'événement via `activitylog`, avec l'auteur.
4. Si `$sendEmail` est vrai, envoie la notification **après le commit**
   (`DB::afterCommit()` ou `dispatch()->afterCommit()`), en file d'attente.
5. Renseigne `email_sent` et `emailed_at` au moment de la mise en file.

Refuse l'appel si `$status` est `null` **et** `$publicMessage` est vide : une
mise à jour vide n'a pas de sens.

### A.4 Interface dans le back-office

Sur la vue détail d'un dossier (`ApplicationResource`), ajoute une action
principale bien visible intitulée **« Informer le candidat »**, qui ouvre une
fenêtre modale contenant :

| Champ | Type | Détail |
|---|---|---|
| Nouveau statut | `Select` | prérempli sur le statut actuel ; peut rester inchangé |
| Message visible par le candidat | `Textarea` | prérempli par un modèle, entièrement modifiable |
| Envoyer un e-mail d'alerte | `Toggle` | activé par défaut |

Le libellé du bouton de confirmation dépend de l'état du `Toggle` :
**« Enregistrer et envoyer »** ou **« Enregistrer sans envoyer »**. La
modale rappelle en une ligne, sous le champ message, que l'e-mail ne contiendra
pas le message.

**Modèles de message.** Le `Textarea` est prérempli en fonction du statut
sélectionné, à partir de textes modifiables dans **Textes du site**, sous les
clés `suivi.modele_nouveau`, `suivi.modele_en_cours`, `suivi.modele_incomplet`,
`suivi.modele_valide`, `suivi.modele_rejete`. Ajoute ces clés au
`SiteContentSeeder` avec des formulations neutres et professionnelles. Le
changement de statut dans la modale met à jour le texte proposé, sauf si
l'administratrice a déjà modifié le champ.

**Historique.** Sous la fiche, affiche la liste des mises à jour :
date, auteur, statut, message, et un indicateur « e-mail envoyé » ou
« non envoyé ».

Accès : les rôles `admin` et `agent` peuvent informer un candidat. La
suppression d'une mise à jour est réservée à `admin`.

### A.5 La notification e-mail

`app/Notifications/ApplicationStatusChanged.php`, en `ShouldQueue`, sur le canal
`mail`, en français.

- Objet : `Votre dossier LN-2026-00147 a été mis à jour`
- Corps : la référence, le nouveau statut en clair, et une invitation à
  consulter la page de suivi muni de sa référence et de son adresse e-mail
- Un bouton vers `/suivre-mon-dossier`, **sans** jeton ni paramètre pré-rempli
- Aucune pièce jointe

Tous les textes de l'e-mail passent par `content()`, sous le préfixe
`email_suivi.`, pour rester modifiables depuis le back-office.

### A.6 Page de suivi

Après vérification réussie de la référence et de l'e-mail, la page affiche
désormais, en plus du statut courant :

- La liste des mises à jour **dont `public_message` n'est pas vide**, de la plus
  récente à la plus ancienne, avec la date et le statut associé.
- Rien d'autre. Ni `internal_notes`, ni le nom ou l'identité de l'agent, ni la
  liste des documents, ni les mises à jour sans message.

### A.7 Garde-fou sur la file d'attente

Un e-mail qui ne part pas sans que personne ne le sache est le risque principal
de ce lot. Ajoute au tableau de bord du back-office un **bandeau d'alerte
visible pour le rôle `admin`** lorsque le plus ancien job en attente dans la
table `jobs` date de plus de dix minutes, avec le message :
« Les e-mails ne partent pas : le worker de file d'attente semble arrêté. »

### A.8 Tests Pest attendus

1. L'action change le statut, crée la mise à jour et envoie la notification
   (`Notification::fake`)
2. Avec le toggle désactivé, la mise à jour est créée et **aucune** notification
   n'est envoyée
3. L'e-mail rendu ne contient pas le contenu de `public_message`
4. La page de suivi affiche les messages publics et **jamais** `internal_notes`
5. Une mise à jour sans statut et sans message est refusée

---

## Lot B — Sécurité des fichiers téléversés

### B.1 Le téléchargement ne doit jamais s'ouvrir dans le navigateur

La validation `mimes` rejette un exécutable renommé, mais pas un PDF légitimement
formé porteur de JavaScript. Ces fichiers sont ouverts chaque jour par
l'administratrice.

Sur la route de téléchargement d'un document, force les en-têtes :

```
Content-Disposition: attachment; filename="..."
X-Content-Type-Options: nosniff
Content-Security-Policy: default-src 'none'
Referrer-Policy: no-referrer
```

Et **désactive toute prévisualisation en ligne** des documents de candidats dans
Filament : pas de visionneuse PDF intégrée, pas de miniature d'image ouverte
depuis le disque privé. Le seul geste possible est le téléchargement.

### B.2 Analyse antivirus, dégradable

Ajoute à `documents` une colonne `scan_status`, enum :
`en_attente`, `sain`, `infecte`, `indisponible` — défaut `en_attente`.

Crée un job `ScanUploadedDocument` en file d'attente, déclenché après un dépôt
réussi, qui appelle **ClamAV** via `clamdscan` ou le socket local.

- Fichier sain → `sain`
- Fichier infecté → `infecte` : le document devient **non téléchargeable**, le
  dossier passe au statut `incomplet`, et une notification part vers les
  comptes `admin`
- ClamAV absent ou injoignable → `indisponible`, sans bloquer quoi que ce soit

Pilotage par `.env` : `DOCUMENT_SCAN_ENABLED=true|false`, à `false` par défaut
dans `.env.example` afin que l'installation en développement fonctionne sans
ClamAV. Documente l'installation de ClamAV dans `DEPLOIEMENT.md`.

Dans le back-office, affiche l'état d'analyse à côté de chaque document.

### B.3 Archive ZIP en flux

Vérifie que `BuildApplicationArchive` **streame** l'archive plutôt que de la
construire en mémoire ou dans un fichier temporaire. Un dossier peut contenir
huit scans de 10 Mo ; la génération ne doit ni saturer `memory_limit`, ni
dépasser `max_execution_time`.

---

## Lot C — Fiabilité des sauvegardes

### C.1 Une sauvegarde qui s'arrête ne déclenche aucune alerte

Le projet ne notifie qu'en cas d'échec. Or une sauvegarde qui ne se lance plus
— cron cassé, disque plein, identifiants S3 expirés — n'échoue pas : elle
n'existe pas.

- Ajoute `backup:monitor` au planificateur, tous les jours à 08h00.
- Configure `monitor_backups` dans `config/backup.php` : âge maximal de la
  dernière sauvegarde à **25 heures**, taille minimale d'archive cohérente avec
  le volume réel, seuil de stockage maximal.

### C.2 Chiffrer les archives

Les archives contiennent des scans de passeports et partent sur un stockage
distant. Active le chiffrement de `spatie/laravel-backup` avec un mot de passe
lu dans `.env` (`BACKUP_ARCHIVE_PASSWORD`), et ajoute la variable à
`.env.example` avec un commentaire explicite.

> Ce mot de passe doit être conservé **hors du serveur**. Perdu, les sauvegardes
> deviennent illisibles. Signale-le clairement dans `DEPLOIEMENT.md`.

### C.3 Procédure de restauration

Ajoute à `DEPLOIEMENT.md` une section **« Test de restauration »** : la marche à
suivre, pas à pas, pour restaurer une sauvegarde sur une base vierge et vérifier
que les documents sont bien récupérés. Précise qu'il faut l'exécuter une fois
avant la mise en production, puis une fois par trimestre.

---

## Lot D — Conservation des données et consentement

### D.1 Consentement au dépôt

Ajoute au formulaire de dépôt une **case à cocher obligatoire**, dont le texte
vient de `content('depot.consentement')` et renvoie vers la politique de
confidentialité. Enregistre sur `applications` :

- `consented_at` (timestamp)
- `privacy_version` (string) — la version du texte acceptée, lue depuis
  `config/brand.php` ou une clé de `site_contents`

Un dépôt sans consentement est refusé côté serveur, pas seulement côté client.

### D.2 Conservation des dossiers vivants

La purge à 90 jours ne concerne aujourd'hui que les dossiers **supprimés**. Un
dossier au statut `valide` conserve indéfiniment un passeport.

Étends `ln:purge-applications` :

- Nouvelle règle : les dossiers sans aucune activité depuis
  `config('retention.months')` mois — valeur par défaut **36** — sont
  définitivement supprimés, fichiers compris.
- Trente jours avant l'échéance, un **rapport e-mail** part vers les comptes
  `admin`, listant les références concernées, afin de permettre une exception.
- `--dry-run` doit couvrir cette nouvelle règle.

### D.3 Effacement sur demande

Ajoute dans `ApplicationResource` une action **« Effacer définitivement »**,
réservée au rôle `admin`, avec confirmation par saisie de la référence. Elle
supprime la ligne et tous les fichiers du disque privé, et journalise
l'opération dans `activitylog` — la seule trace qui subsiste.

### D.4 Politique de confidentialité

Complète le contenu de la page dans `SiteContentSeeder` : nature des données
collectées, finalité, durée de conservation, destinataires, procédure de demande
d'effacement et adresse de contact. Laisse en clair les éléments que la cliente
seule peut fournir, sous la forme `[À COMPLÉTER PAR LA CLIENTE]`. N'invente
aucune mention légale, aucun numéro d'immatriculation, aucune adresse.

---

## Lot E — Limiteur de débit et réseaux partagés

Au Cameroun, une grande partie des abonnés mobiles partagent une même adresse IP
publique via le CGNAT, et les cybercafés davantage encore. Un plafond de cinq
tentatives par minute et par IP sur `/suivre-mon-dossier` peut bloquer des
candidats parfaitement légitimes.

- Limiteur `suivi` : change la clé pour combiner l'adresse IP **et la référence
  saisie** (`sha1($ip.'|'.$reference)`), plafond **10 par minute**. Ajoute un
  second limiteur, purement par IP, à **60 par minute**, pour continuer à
  freiner une énumération massive.
- Limiteur `depot` : conserve 5 par minute, mais ajoute un commentaire dans
  `AppServiceProvider` expliquant qu'il faudra passer à une clé combinant IP et
  adresse e-mail si des blocages sont constatés sur le terrain.
- Vérifie que `TrustProxies` est correctement configuré : derrière un reverse
  proxy mal réglé, **toutes** les requêtes partagent l'IP du proxy et le
  limiteur bloque tout le monde.

Ajoute un test : deux références différentes depuis la même IP ne se bloquent pas
mutuellement.

---

## Ce que tu ne dois pas faire

- Ne mets pas le message de l'administratrice dans l'e-mail
- Ne crée pas de lien de connexion automatique ni de jeton dans l'e-mail
- Ne rends pas `internal_notes` visible sur la page de suivi, sous aucune forme
- Ne crée pas de compte candidat, l'inscription publique reste fermée
- Ne remplace pas les limiteurs nommés par des `throttle:x,y` anonymes
- N'écris aucun texte visible en dur : tout passe par `content()`
- N'invente ni mention légale, ni tarif, ni témoignage
- Ne passe pas au lot suivant sans ma validation explicite

## Critères d'acceptation du lot complet

- `composer lint:check`, `composer types:check` et `php artisan test` passent
- Le parcours complet est vérifiable à la main : dépôt d'un dossier, changement
  de statut avec message depuis le back-office, réception de l'e-mail d'alerte,
  puis lecture du message sur la page de suivi avec la référence et l'e-mail
- `README.md` et `DEPLOIEMENT.md` sont mis à jour : nouvelle table, nouvelle
  commande, nouvelles variables `.env`, installation de ClamAV, procédure de
  restauration
