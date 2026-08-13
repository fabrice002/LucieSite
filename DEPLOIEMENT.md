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
- [ ] Double authentification activée sur le compte de l'administratrice
- [ ] Certificat TLS valide, redirection HTTP → HTTPS

### Sauvegardes

- [ ] `BACKUP_DISKS` désigne un **stockage distant**
- [ ] `mysqldump` est accessible — sinon renseigner `DB_DUMP_BINARY_PATH`,
      faute de quoi la base n'est pas sauvegardée
- [ ] `php artisan backup:run` réussit
- [ ] `php artisan backup:list` montre l'archive sur le disque distant
- [ ] **Une restauration a été testée** — une sauvegarde jamais restaurée n'est
      pas une sauvegarde

### Fonctionnement

- [ ] `php artisan schedule:list` affiche les quatre tâches
- [ ] Le worker tourne (`supervisorctl status`)
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

## 6. Supervision

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
