# Mise en production — LN Immigration

Procédure de déploiement et checklist à parcourir **avant** d'ouvrir le site au
public. Le site héberge des scans de passeports : aucun point de la section
« Sécurité » n'est optionnel.

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

## 4. Checklist avant ouverture

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
- [ ] Export ZIP d'un dossier
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

## 5. Mises à jour

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

> `queue:restart` est indispensable : sans lui, les workers continuent
> d'exécuter l'ancien code.

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

## 7. Supervision

| À surveiller | Commande |
|---|---|
| Sauvegardes | `php artisan backup:list` |
| Files d'attente | `php artisan queue:failed` |
| Espace disque | `du -sh storage/app/private` |
| Journal d'activité | table `activity_log` |
| Erreurs | `storage/logs/laravel.log` |

Points d'attention :

- **`storage/app/private` grossit en continu.** La purge à 90 jours ne concerne
  que les dossiers supprimés. Surveillez l'espace disque.
- **Les échecs de sauvegarde sont notifiés par e-mail.** Si vous ne recevez
  jamais rien, vérifiez que le worker tourne — sinon l'alerte elle-même reste
  bloquée en file.
