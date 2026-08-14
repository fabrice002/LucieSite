# Mise en production — LN Immigration

Procédure de déploiement et checklist à parcourir **avant** d'ouvrir le site au
public. Le site héberge des scans de passeports : aucun point de la section
« Sécurité » n'est optionnel.

## Sommaire

1. [Prérequis serveur](#1-prérequis-serveur)
2. [Déploiement](#2-déploiement)
3. [Processus permanents](#3-processus-permanents)
4. [Reverse proxy et limiteurs de débit](#4-reverse-proxy-et-limiteurs-de-débit)
5. [Checklist avant ouverture](#5-checklist-avant-ouverture)
6. [Test de restauration](#6-test-de-restauration)
7. [Mises à jour](#7-mises-à-jour)
8. [Supervision](#8-supervision)

## Les quatre pannes silencieuses

Avant toute chose, retenez ces quatre configurations dont l'oubli **ne produit
aucune erreur** — tout semble fonctionner :

| Oubli | Conséquence invisible |
|---|---|
| Worker de file arrêté | Aucun e-mail ne part. Ni au candidat, ni au cabinet. |
| `BACKUP_ARCHIVE_PASSWORD` vide | Les archives partent **en clair** sur le stockage distant. |
| `TRUSTED_PROXIES` vide derrière un proxy | Toutes les requêtes partagent une IP : les limiteurs bloquent tout le monde. |
| `DB_DUMP_BINARY_PATH` manquant | Les sauvegardes ne contiennent **pas la base**. |

Chacune est vérifiée par un point de la checklist en section 5.

---

## 1. Prérequis serveur

### PHP 8.3 ou supérieur

Extensions **obligatoires** :

| Extension | Pourquoi |
|---|---|
| `zip` | Export ZIP des dossiers et sauvegardes |
| `fileinfo` | Validation `mimes` — sans elle, un exécutable renommé passerait |
| `pdo_mysql` | Base de données |
| `gd` | Traitement des images |
| `intl`, `mbstring`, `openssl`, `curl` | Socle Laravel |
| `redis` | Files d'attente et cache en production |

Vérification :

```bash
php -m | grep -E 'zip|fileinfo|pdo_mysql|gd|redis'
```

### Valeurs `php.ini`

Les candidats envoient des scans de 5 à 10 Mo, parfois plusieurs à la fois.

```ini
upload_max_filesize = 12M
post_max_size       = 60M
memory_limit        = 256M
max_execution_time  = 120
max_file_uploads    = 30
```

> Avec FilePond, les fichiers arrivent par tranches de 512 ko : `post_max_size`
> n'est donc sollicité que par le parcours **sans JavaScript**. Gardez-le
> confortable, c'est le filet de sécurité.

### Serveur web

**Nginx** :

```nginx
client_max_body_size 60M;
client_body_timeout  120s;
```

**Apache** — dans le `VirtualHost` :

```apache
LimitRequestBody 62914560
```

### Autres

- MySQL 8 ou MariaDB 10.11+
- Redis (files d'attente et cache)
- Certificat TLS valide — le site transporte des pièces d'identité

### ClamAV — analyse antivirus des pièces déposées

La validation `mimes` rejette un exécutable renommé, mais pas un PDF
légitimement formé porteur de JavaScript. Ces fichiers sont ouverts chaque jour
par l'administratrice : ClamAV apporte une seconde barrière.

```bash
sudo apt install clamav clamav-daemon
sudo systemctl enable --now clamav-freshclam clamav-daemon
```

Le worker doit pouvoir lire les fichiers analysés :

```bash
sudo usermod -a -G www-data clamav
sudo chmod 750 /var/www/ln-immigration/storage/app/private
```

Puis dans `.env` :

```dotenv
DOCUMENT_SCAN_ENABLED=true
DOCUMENT_SCAN_COMMAND=clamdscan
```

Vérification :

```bash
which clamdscan
clamdscan --version
# Fichier de test EICAR, inoffensif, reconnu par tous les antivirus
curl -s https://secure.eicar.org/eicar.com -o /tmp/eicar.txt && clamdscan --fdpass /tmp/eicar.txt
```

> **L'analyse est volontairement dégradable.** ClamAV absent, arrêté ou trop
> lent ne bloque aucun dépôt : la pièce est marquée « Analyse indisponible » et
> reste téléchargeable. Un antivirus en panne ne doit jamais empêcher un
> candidat de déposer son dossier, ni le cabinet de travailler.
>
> Seul un fichier **formellement reconnu infecté** est bloqué : le
> téléchargement est refusé, le dossier passe au statut « incomplet » et les
> comptes `admin` reçoivent une alerte.
>
> Surveillez la proportion de pièces « Analyse indisponible » dans le
> back-office : au-delà de quelques cas, c'est que ClamAV ne répond plus.

---

## 2. Déploiement

```bash
git clone <url-du-depot> /var/www/ln-immigration
cd /var/www/ln-immigration

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.production.example .env
# Renseigner chaque valeur vide, puis :
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=SiteContentSeeder --force
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Permissions

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
# Les documents des candidats ne doivent pas être lisibles par tous
chmod -R 750 storage/app/private
```

### Premier compte administrateur

```bash
php artisan ln:create-user --role=admin
```

---

## 3. Processus permanents

### Worker de file d'attente

Les notifications partent en file : sans worker, **aucun e-mail n'est envoyé**.

`/etc/supervisor/conf.d/ln-worker.conf` :

```ini
[program:ln-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ln-immigration/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/ln-immigration/storage/logs/worker.log
```

```bash
supervisorctl reread && supervisorctl update && supervisorctl start ln-worker:*
```

### Planificateur

```cron
* * * * * cd /var/www/ln-immigration && php artisan schedule:run >> /dev/null 2>&1
```

---

## 4. Reverse proxy et limiteurs de débit

Le site est presque toujours servi derrière un frontal — Nginx, un load
balancer, ou Cloudflare. **Il faut le déclarer**, sinon Laravel voit l'adresse
du proxy et non celle du visiteur : toutes les requêtes partagent la même IP et
les limiteurs de débit bloquent tout le monde après quelques consultations.

C'est la panne la plus déroutante à diagnostiquer, parce que rien n'est en
erreur : le site fonctionne, mais les candidats reçoivent des 429.

```dotenv
# Le serveur n'est joignable QUE par le proxy
TRUSTED_PROXIES=*

# Ou, plus sûr, la liste des proxies
TRUSTED_PROXIES=10.0.0.1,10.0.0.2
```

> `*` ne se pose que si le serveur applicatif est inaccessible directement.
> Sinon, l'en-tête `X-Forwarded-For` devient falsifiable et le limiteur se
> contourne en changeant d'IP à volonté.

Le frontal doit transmettre les en-têtes :

```nginx
proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-Host  $host;
```

Et ne pas mettre les réponses en tampon, sans quoi le téléchargement groupé
d'un dossier attendrait la fin de la compression avant d'émettre le premier
octet — et pourrait dépasser le délai du proxy :

```nginx
proxy_buffering off;
proxy_read_timeout 300s;
```

L'application envoie déjà `X-Accel-Buffering: no` sur ces réponses ; ces
directives sont le filet de sécurité si le frontal l'ignore.

Vérification, après déploiement **et après `config:cache`** :

```bash
php artisan config:cache
php artisan tinker --execute="echo config('proxies.trusted');"
# Puis, depuis un navigateur, comparez l'IP vue par le site avec
# celle renvoyée par https://api.ipify.org
```

> Le réglage est lu depuis `config/proxies.php` et appliqué par
> `AppServiceProvider`, et non dans `bootstrap/app.php`. À cet endroit, seul
> `env()` est disponible, et il renvoie `null` dès que la configuration est
> mise en cache — c'est-à-dire précisément en production. Le réglage aurait été
> silencieusement ignoré là où il compte.

### Plafonds en vigueur

| Route | Plafond | Clé |
|---|---|---|
| `/deposer-mon-dossier` | 5 / min | IP |
| `/suivre-mon-dossier` | 10 / min | IP + référence |
| `/suivre-mon-dossier` | 60 / min | IP |
| `/televersement` | 300 / min | IP |

La clé du suivi combine l'IP **et la référence** parce qu'au Cameroun une
grande part des abonnés mobiles partagent une même adresse publique via le
CGNAT, et les cybercafés davantage encore. Deux candidats derrière la même IP
ne se gênent donc pas.

**Si des blocages sont signalés sur le dépôt**, appliquez la même logique :
combinez l'IP et l'adresse e-mail. La ligne exacte figure en commentaire dans
`app/Providers/AppServiceProvider.php`.

---

## 5. Checklist avant ouverture

### Environnement

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false` — **critique** : `true` exposerait les identifiants de la base
- [ ] `APP_URL` en `https://`
- [ ] `APP_KEY` généré
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`
- [ ] `MAIL_ADMIN_ADDRESS` renseigné, sinon l'administratrice n'est pas prévenue
- [ ] `.env` absent du dépôt Git

### Sécurité

- [ ] `config/filesystems.php` : le disque `local` pointe sur `storage/app/private`
- [ ] `'serve' => false` sur le disque `local` — sinon une seconde voie de
      téléchargement contourne la Policy
- [ ] `https://votre-domaine/storage/documents/...` renvoie 403 ou 404
- [ ] `/register` renvoie 404
- [ ] Un compte sans rôle est refusé sur `/admin`
- [ ] `DOCUMENT_SCAN_ENABLED=true` et `clamdscan --version` répond
- [ ] Un document téléchargé depuis `/admin` arrive en **pièce jointe**,
      jamais rendu dans l'onglet (vérifier l'en-tête `Content-Disposition`)
- [ ] Double authentification activée sur le compte de l'administratrice
- [ ] Certificat TLS valide, redirection HTTP → HTTPS
- [ ] **`TRUSTED_PROXIES` renseigné** si le site est derrière un frontal, et
      vérifié **après** `config:cache` (section 4)
- [ ] L'IP vue par le site correspond à celle du visiteur, pas à celle du proxy

### Sauvegardes

- [ ] `BACKUP_DISKS` désigne un **stockage distant**
- [ ] `mysqldump` est accessible — sinon renseigner `DB_DUMP_BINARY_PATH`,
      faute de quoi la base n'est pas sauvegardée
- [ ] **`BACKUP_ARCHIVE_PASSWORD` est renseigné.** Sans lui, `laravel-backup`
      produit une archive parfaitement valide et **parfaitement lisible**, sans
      le moindre avertissement : les scans de passeports partiraient en clair
      sur un stockage qui n'est pas sous votre contrôle
- [ ] Ce mot de passe est conservé **hors du serveur** (gestionnaire de mots de
      passe). Perdu, les sauvegardes deviennent définitivement illisibles
- [ ] `php artisan backup:run` réussit
- [ ] `php artisan backup:list` montre l'archive sur le disque distant
- [ ] `php artisan backup:monitor` répond « healthy » — il contrôle l'âge
      (25 h), la taille minimale et le chiffrement effectif
- [ ] **Une restauration a été testée** (section 6) — une sauvegarde jamais
      restaurée n'est pas une sauvegarde

### Fonctionnement

- [ ] `php artisan schedule:list` affiche les quatre tâches
- [ ] Le worker tourne (`supervisorctl status`)
- [ ] **Le SMTP sortant passe** : `php artisan ln:test-mail votre.adresse@exemple.com`
      Cette commande envoie sans passer par la file. Un échec « unreachable
      network » ou « connection could not be established » signale un port
      bloqué (25, 465, 587), pas un problème d'identifiants. Vérifier avec
      `Test-NetConnection smtp.exemple.com -Port 587` ou `nc -zv`.
- [ ] Dépôt de bout en bout : le candidat **et** l'administratrice reçoivent leur e-mail
- [ ] Le suivi par référence + e-mail affiche le statut
- [ ] Une mauvaise adresse e-mail ne révèle rien
- [ ] Téléchargement d'un document depuis `/admin`
- [ ] **Export ZIP d'un dossier volumineux** (plusieurs scans) : le
      téléchargement doit démarrer immédiatement. S'il attend plusieurs
      secondes avant de commencer, le frontal met la réponse en tampon —
      voir `proxy_buffering off` en section 4
- [ ] **Test sur un vrai téléphone Android en 3G** : dépôt avec deux scans
      photographiés, coupure du réseau en cours d'envoi, puis reprise

### Contenu

- [ ] Plus aucun `[À COMPLÉTER PAR LA CLIENTE]` sur le site
- [ ] Mentions légales et politique de confidentialité rédigées
- [ ] Coordonnées du cabinet renseignées
- [ ] Logo définitif en place (`resources/views/components/app-logo-icon.blade.php`,
      `public/favicon.svg`)

### Qualité

- [ ] `composer lint:check` passe
- [ ] `composer types:check` passe
- [ ] `php artisan test` passe

---

## 6. Test de restauration

**Une sauvegarde jamais restaurée n'est pas une sauvegarde.** Cette procédure
est à exécuter **une fois avant la mise en production**, puis **une fois par
trimestre**.

Elle se déroule entièrement sur une base et un dossier de travail séparés :
la production n'est jamais touchée.

### 1. Récupérer l'archive

```bash
php artisan backup:list
```

Notez le nom de la dernière archive, puis téléchargez-la depuis le stockage
distant vers un dossier de travail :

```bash
mkdir -p /tmp/restauration && cd /tmp/restauration
aws s3 cp "s3://VOTRE-BUCKET/LN Immigration/2026-08-14-01-30-00.zip" .
```

### 2. Ouvrir l'archive chiffrée

```bash
unzip -P "$BACKUP_ARCHIVE_PASSWORD" 2026-08-14-01-30-00.zip -d contenu/
```

> Si le mot de passe est refusé ou introuvable, **arrêtez-vous ici** : la
> sauvegarde est inexploitable. C'est précisément ce que ce test sert à
> découvrir aujourd'hui plutôt que le jour d'un incident.

Vous devez trouver :

- `db-dumps/mysql-<base>.sql` — le dump de la base
- `storage/app/private/documents/…` — les pièces des candidats
- `storage/app/public/temoignages/…` — les photos des témoignages

### 3. Restaurer la base dans une base vierge

```bash
mysql -u root -p -e "CREATE DATABASE ln_restauration CHARACTER SET utf8mb4;"
mysql -u root -p ln_restauration < contenu/db-dumps/mysql-*.sql
```

### 4. Vérifier le contenu

```bash
mysql -u root -p ln_restauration -e "
  SELECT COUNT(*) AS dossiers FROM applications;
  SELECT COUNT(*) AS documents FROM documents;
  SELECT COUNT(*) AS comptes FROM users;
  SELECT reference, status, created_at FROM applications ORDER BY id DESC LIMIT 5;
"
```

Puis vérifiez que **les fichiers correspondent aux lignes** — le point le plus
souvent négligé, et celui qui fait échouer les restaurations réelles :

```bash
# Nombre de documents attendus d'après la base
mysql -u root -p ln_restauration -sN -e "SELECT COUNT(*) FROM documents;"

# Nombre de fichiers réellement présents dans l'archive
find contenu/storage/app/private/documents -type f | wc -l
```

Les deux nombres doivent coïncider. Ouvrez enfin **un** document au hasard pour
confirmer qu'il n'est pas corrompu.

### 5. Nettoyer

```bash
mysql -u root -p -e "DROP DATABASE ln_restauration;"
rm -rf /tmp/restauration
```

### Consigner le résultat

| Date | Archive testée | Base restaurée | Fichiers concordants | Par |
|---|---|---|---|---|
| | | | | |

---

## 7. Mises à jour

```bash
php artisan down --render="errors::503"

git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=SiteContentSeeder --force   # ajoute les nouvelles clés

php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart

php artisan up
```

> `SiteContentSeeder` n'écrase jamais un texte modifié depuis le back-office :
> il ajoute uniquement les clés absentes.

> `queue:restart` est **indispensable** : sans lui, les workers gardent
> l'ancien code en mémoire et continuent de l'exécuter.

Après chaque mise à jour :

```bash
php artisan ln:test-mail vous@exemple.com   # le SMTP répond toujours
php artisan backup:monitor                  # les sauvegardes sont saines
php artisan schedule:list                   # les cinq tâches sont là
```

---

## 8. Supervision

| À surveiller | Commande |
|---|---|
| Sauvegardes | `php artisan backup:list` |
| Files d'attente | `php artisan queue:failed` |
| Espace disque | `du -sh storage/app/private` |
| Journal d'activité | table `activity_log` |
| Erreurs | `storage/logs/laravel.log` |

### Rythme conseillé

| Fréquence | À faire |
|---|---|
| Quotidien | Lire les alertes reçues par e-mail. Aucune alerte **n'est pas** une bonne nouvelle en soi : voir ci-dessous. |
| Hebdomadaire | `php artisan queue:failed` et `php artisan backup:list` |
| Mensuel | Espace disque, proportion de pièces en « Analyse indisponible » |
| Trimestriel | **Test de restauration** (section 6) |
| Annuel | Renouveler `BACKUP_ARCHIVE_PASSWORD` et les identifiants de stockage |

### Points d'attention

- **`storage/app/private` grossit en continu.** La purge à 90 jours ne concerne
  que les dossiers supprimés ; celle à 36 mois, les dossiers sans activité. Un
  cabinet actif accumule malgré tout. Surveillez l'espace disque.

- **Ne pas recevoir d'alerte n'est pas rassurant en soi.** Les notifications
  passent elles-mêmes par la file d'attente : si le worker est arrêté, l'alerte
  qui vous préviendrait reste bloquée. Le bandeau du tableau de bord est le seul
  signal qui ne dépend pas de la file — c'est pourquoi il existe.

- **Surveillez la proportion de pièces « Analyse indisponible ».** Au-delà de
  quelques cas isolés, ClamAV ne répond plus et les fichiers entrent sans être
  analysés.

- **Le journal d'activité est la seule trace après un effacement.** Ne le purgez
  pas : il atteste qui a supprimé quoi et quand, sans conserver la moindre
  donnée de candidat.

### Vérification mensuelle en une commande

```bash
php artisan backup:monitor \
  && php artisan queue:failed \
  && php artisan ln:purge-applications --dry-run \
  && du -sh storage/app/private
```

Le `--dry-run` annonce les dossiers qui seront effacés dans les trente jours,
sans rien supprimer : c'est le moment de décider d'une éventuelle exception.
