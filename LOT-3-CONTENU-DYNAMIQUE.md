# LN Immigration — Lot 3 : contenu dynamique, identité visuelle et design

## 0. À lire avant de commencer

L'application existe et fonctionne. `README.md` et `DEPLOIEMENT.md` sont à jour
et font autorité. Lis-les en entier, ainsi que `CLAUDE.md`,
`LOT-2-AMELIORATIONS.md`, `app/Actions/`, `app/Models/SiteContent.php`,
`SiteContentSeeder`, `SiteContentObserver`, `config/brand.php` et les ressources
Filament existantes.

Règles impératives, inchangées :

- Tu respectes les conventions en place. Pas de nouvelle bibliothèque front, pas
  de nouvelle façon de structurer la logique métier.
- Tu n'ajoutes **aucune dépendance Composer ou npm** sans me demander d'abord.
  Ce lot est conçu pour être réalisable avec Filament 5, Tailwind 4 et
  l'extension `gd` déjà présente.
- **Un lot à la fois. À la fin de chaque lot tu t'arrêtes, tu résumes, tu attends
  ma validation.**
- `composer lint:check`, `composer types:check` et `php artisan test` passent à
  la fin de chaque lot.
- Tu mets à jour `README.md` et `DEPLOIEMENT.md` au fil de l'eau.

Ordre : **F, G, H, I**. Le lot I est bloquant pour la mise en production.

---

## Lot F — Rendre le contenu réellement dynamique

### F.1 Le problème

`site_contents` stocke un JSON à plat par bloc. C'est parfait pour un titre ou un
paragraphe, et inadapté dès qu'il s'agit d'une **liste** : la cliente ne peut ni
ajouter un dixième service, ni créer une section de FAQ, ni poser une image dans
une page. Le nombre de questions de la FAQ est aujourd'hui figé à six par le
seeder — c'est une limite arbitraire qui n'a aucune raison d'exister.

L'objectif de ce lot : **la cliente doit pouvoir construire ses pages Services et
À propos elle-même**, ajouter autant d'éléments qu'elle veut, les réordonner par
glisser-déposer, y placer des images, et publier ou dépublier chaque élément
sans intervention technique.

`site_contents` reste en place pour les textes uniques (titres de pages,
libellés, textes d'e-mails). On lui ajoute quatre modèles.

### F.2 `services` — une ressource à part entière

Les cabinets d'immigration professionnels traitent chaque programme comme une
page à part, référencée séparément : Entrée Express, PSTQ, permis d'études,
regroupement familial, visa visiteur, permis de travail. C'est ce qui apporte le
trafic organique.

| colonne | type | notes |
|---|---|---|
| id | bigint | |
| slug | string, unique | `entree-express` |
| title | string | |
| summary | text | affiché sur la carte, 2 à 3 lignes |
| body | longText | texte riche, page de détail |
| image_path | string, nullable | disque public |
| icon | string, nullable | nom d'icône Heroicon |
| highlight | string, nullable | mention courte, ex. « Le plus demandé » |
| sort_order | integer | |
| is_published | boolean, défaut `false` | |
| meta_title, meta_description | string, nullable | référencement |
| timestamps | | |

- Routes : `/services` (liste des services publiés) et `/services/{slug}`
- `/sitemap.xml` intègre dynamiquement les services publiés
- Un service dépublié renvoie 404 sur sa page de détail

### F.3 `faq_categories` et `faqs` — sans limite de nombre

| `faq_categories` | type |
|---|---|
| id, name, slug, sort_order, is_published, timestamps | |

| `faqs` | type |
|---|---|
| id | bigint |
| faq_category_id | foreignId, cascadeOnDelete |
| question | string |
| answer | longText, texte riche |
| sort_order | integer |
| is_published | boolean, défaut `true` |
| timestamps | |

Page `/faq` :

- Questions groupées par catégorie, en accordéon
- Un champ de recherche côté client filtre les questions à la frappe
- Une ancre par question (`#faq-42`) pour permettre le partage d'un lien direct
- Balisage **JSON-LD `FAQPage`** généré à partir des questions publiées, pour
  l'affichage enrichi dans les moteurs de recherche
- Une catégorie dépubliée masque toutes ses questions
- Une catégorie sans question publiée n'apparaît pas

Le seeder fournit trois catégories d'exemple et une douzaine de questions
courantes. **Aucun plafond n'est codé nulle part.**

### F.4 `page_sections` — le constructeur de pages

Pour l'accueil, À propos et le haut de la page Services, la cliente doit pouvoir
empiler des blocs.

| colonne | type | notes |
|---|---|---|
| id | bigint | |
| page | string | `accueil`, `a-propos`, `services`, `contact` |
| type | enum | voir ci-dessous |
| sort_order | integer | |
| is_published | boolean, défaut `true` | |
| data | json | contenu du bloc, structure dépendant du type |
| timestamps | | |

Types à implémenter :

| type | contenu |
|---|---|
| `hero` | sur-titre, titre, texte, image de fond, libellé et lien du bouton principal, libellé et lien du bouton secondaire |
| `texte_image` | titre, texte riche, image, position de l'image (gauche/droite) |
| `cartes` | titre de section, liste de cartes (icône ou image, titre, texte, lien) |
| `etapes` | titre, liste d'étapes numérotées (titre, description, icône) |
| `chiffres` | liste de valeurs (nombre, libellé) |
| `galerie` | titre, liste d'images avec légende |
| `citation` | texte, auteur, fonction |
| `cta` | titre, texte, libellé et lien du bouton |
| `logos` | titre, liste de logos ou labels (image, légende, lien) |

Édition dans Filament avec un champ **Builder** : la cliente ajoute un bloc,
choisit son type, remplit un formulaire adapté, et réordonne par glisser-déposer.
Les listes internes sont des **Repeater**, sans limite d'éléments.

Chaque type correspond à un partial Blade sous `resources/views/sections/`. Une
vue affiche les sections de sa page, publiées, triées par `sort_order`. Un type
inconnu est ignoré silencieusement plutôt que de casser la page.

### F.5 `team_members` — l'équipe

| colonne | type |
|---|---|
| id, name, role, bio (text, nullable), photo_path, sort_order, is_published, timestamps | |

Affiché sur la page À propos si au moins un membre est publié, masqué sinon.

### F.6 Les images

Toutes les images de contenu vont sur le **disque public**, sous
`images/services/`, `images/sections/`, `images/equipe/`. Les documents de
candidats restent sur le disque privé : cette séparation ne bouge pas.

Sur chaque champ `FileUpload` de Filament :

- éditeur d'image activé (recadrage, rotation) — fonctionnalité native, aucune
  dépendance à ajouter
- redimensionnement au téléversement, largeur cible **1600 px** maximum
- taille maximale 4 Mo, formats `jpeg`, `png`, `webp`
- champ **texte alternatif obligatoire** à côté de chaque image, pour
  l'accessibilité et le référencement
- suppression du fichier sur le disque quand l'enregistrement est supprimé

Dans les vues publiques, chaque image porte `loading="lazy"`, `width`, `height`
et son texte alternatif. Les visiteurs sont en 3G : une image de 3 Mo non
dimensionnée ruine la page.

### F.7 Cache

Services, FAQ, sections et équipe sont mis en cache comme `site_contents`, avec
un observer par modèle qui invalide la clé correspondante à chaque sauvegarde ou
suppression. Une modification faite dans le back-office doit être visible
immédiatement sur le site public, sans commande à lancer.

### F.8 Tests attendus

1. Un service dépublié n'apparaît ni dans la liste ni via son slug
2. Une catégorie de FAQ dépubliée masque ses questions
3. Une section dépubliée n'est pas rendue
4. La sauvegarde d'un service invalide le cache et le changement est visible
5. Le `sitemap.xml` contient les services publiés et eux seuls

---

## Lot G — Identité visuelle pilotable depuis le back-office

### G.1 Ce que la cliente doit pouvoir changer seule

Aujourd'hui le logo et les couleurs vivent dans `config/brand.php` et `.env` :
inaccessibles sans développeur. Crée une section **Apparence** dans le
back-office, réservée au rôle `admin`, permettant de régler :

- couleur principale, couleur secondaire, couleur d'accent
- couleur du texte sur fond coloré
- logo clair, logo sombre, favicon
- police parmi une **liste blanche** de trois ou quatre familles auto-hébergées
- activation ou non du thème sombre côté public

Stockage dans une table `site_settings` (`key`, `value`, `type`), avec un helper
`setting('couleur_principale', '#1e40af')` mis en cache et invalidé par observer.
`config/brand.php` conserve les valeurs de repli si la table est vide.

### G.2 Comment appliquer les couleurs

Tailwind 4 se configure en CSS, avec des variables. Injecte les valeurs dans le
layout :

```blade
<style>
  :root {
    --color-primary: {{ setting('couleur_principale') }};
    --color-primary-contrast: {{ setting('couleur_texte_sur_principale') }};
    --color-accent: {{ setting('couleur_accent') }};
  }
</style>
```

> **N'essaie pas de générer des classes Tailwind dynamiquement** — `bg-{{ $c }}`
> ne fonctionne pas, le compilateur ne voit pas ces classes au build et elles
> n'existent jamais dans le CSS produit. Tout passe par les variables CSS.

### G.3 Garde-fou de contraste

Donner un sélecteur de couleur à une cliente sans garde-fou produit tôt ou tard
un site jaune sur blanc, illisible et non conforme.

À la sauvegarde, calcule le **ratio de contraste WCAG** entre la couleur
principale et la couleur de texte associée. Sous **4,5:1**, affiche un
avertissement clair dans Filament indiquant le ratio obtenu et le minimum requis.
L'enregistrement reste possible — c'est son site — mais elle est prévenue.

### G.4 Palettes prêtes à l'emploi

Propose cinq palettes cohérentes, applicables en un clic, avant même de laisser
choisir des couleurs libres : par exemple « Bleu institutionnel », « Marine et
or », « Vert forêt », « Bordeaux et crème », « Ardoise et cuivre ». Chacune fixe
les trois couleurs et la couleur de texte associée, toutes conformes au contraste
minimum. La plupart du temps elle choisira une palette et n'ira jamais plus loin.

### G.5 Logo et favicon

Le téléversement du logo se fait depuis **Apparence**, plus seulement par `.env`.
Après un changement de logo ou de couleur d'icône, un bouton **« Régénérer les
icônes »** exécute `ln:generate-icons` et affiche le résultat. La logique
existante de `config/brand.php` et du monogramme de repli est conservée.

### G.6 Tests attendus

1. La couleur enregistrée apparaît dans la variable CSS de la page publique
2. Un couple de couleurs à faible contraste déclenche l'avertissement
3. L'application d'une palette met à jour toutes les valeurs concernées
4. Sans valeur en base, les valeurs de `config/brand.php` sont utilisées

---

## Lot H — Refonte du design public

### H.1 Conventions du secteur à reprendre

Ces éléments reviennent sur pratiquement tous les sites professionnels
d'immigration au Canada. Ils ne sont pas décoratifs : ils répondent à l'anxiété
d'un candidat qui confie un projet de vie et de l'argent à un inconnu.

**Un appel à l'action dominant, en haut à droite de la navigation et dans le
hero.** Un seul, répété : « Déposer mon dossier ». Un second, secondaire :
« Poser une question ». La bonne pratique constante du secteur est un bouton
contrasté, présent dès le premier écran, et repris en bas de chaque page.

**Une bande de réassurance sous le hero** : années d'expérience, nombre de
dossiers accompagnés, pays couverts, appartenance professionnelle. Chiffres
réels uniquement — voir la section H.3.

**Les services en cartes**, chacune menant à sa page détaillée. C'est le schéma
retenu par les cabinets qui traitent chaque programme comme une page à part.

**Un bloc « Notre processus »** en trois à cinq étapes numérotées : évaluation du
profil, préparation du dossier, dépôt, suivi. Le candidat veut savoir ce qui va
lui arriver avant de s'engager.

**Les témoignages avec prénom, pays et programme obtenu**, plus une photo quand
la personne l'autorise. Un témoignage anonyme sans contexte ne rassure personne.

**Les coordonnées complètes dans le pied de page** : téléphone, WhatsApp,
adresse physique, e-mail, horaires. L'adresse physique est un signal de
légitimité déterminant dans ce secteur.

**Photos authentiques plutôt que banques d'images.** Les analyses récentes du
secteur convergent : les visuels génériques sont le premier signal de défiance.
Prévois les emplacements ; la cliente fournira les photos. En attendant, utilise
des aplats de couleur de la charte, jamais une photo d'agence.

**Accessibilité** : contraste conforme, tailles de police confortables,
navigation au clavier, textes alternatifs. Ce n'est pas une option en 2026.

### H.2 Sites de référence à étudier

À consulter pour la structure et la hiérarchie de l'information, **jamais pour
copier des textes ou des images** :

- `canadabychoice.com` — mise en avant de la différence entre évaluation gratuite et consultation payante
- `caimservices.com` — parcours en trois étapes très lisible
- `canadavisa.com` — organisation par programme, référence du secteur
- `clio.com/blog/website-design-for-immigration-lawyers/` — analyse des bonnes pratiques

### H.3 Ce que le site ne doit jamais faire

Ce secteur attire beaucoup de fraude, et les candidats d'Afrique centrale en sont
une cible fréquente. Le site doit être irréprochable sur ces points, autant pour
protéger les candidats que la réputation de la cliente.

- **Aucune promesse de résultat.** Ni « visa garanti », ni « 98 % de réussite »,
  ni compte à rebours artificiel. Le contenu par défaut ne contient aucune de ces
  formulations et le seeder n'en propose aucune.
- **Aucune statistique inventée.** Les blocs `chiffres` sont livrés **vides**,
  avec un texte d'aide indiquant que seules des données réelles doivent y figurer.
- **Statut professionnel affiché clairement.** Une page ou un bloc dédié doit
  indiquer à quel titre le cabinet intervient et, le cas échéant, le numéro
  d'enregistrement du consultant réglementé. Laisse
  `[À COMPLÉTER PAR LA CLIENTE]` — n'invente aucun numéro, aucune affiliation,
  aucun agrément.
- **Tarifs et périmètre explicites** : ce qui est inclus, ce qui ne l'est pas.
- **Aucun témoignage fictif** dans le seeder. Les exemples portent la mention
  « exemple — à remplacer » et sont non publiés par défaut.

### H.4 Ce qui reste inchangé

Le formulaire de dépôt, la page de suivi et le back-office ne changent pas dans
ce lot. La refonte est purement publique : mise en page, hiérarchie visuelle,
composants réutilisables. Aucune régression sur le fonctionnement sans
JavaScript, ni sur les performances en 3G.

---

## Lot I — Conservation : aucune suppression sans décision humaine

### I.1 Le changement demandé

Aujourd'hui, un dossier sans activité depuis 36 mois est **supprimé
automatiquement**, après un préavis de 30 jours. Ce n'est plus acceptable : la
destruction de pièces d'identité ne doit jamais reposer sur un silence.

Nouveau fonctionnement :

1. À l'échéance, le dossier passe au statut interne **`en_attente_de_decision`**.
   Rien n'est supprimé. Les fichiers restent intacts.
2. Une section **« Dossiers arrivés à échéance »** apparaît dans le back-office,
   listant ces dossiers avec, pour chacun, deux actions :
   **« Conserver 12 mois de plus »** et **« Effacer définitivement »**.
3. Un **bandeau permanent** s'affiche sur le tableau de bord tant qu'au moins un
   dossier attend une décision.
4. Un **rappel e-mail** part aux comptes `admin` à J-30, J-7, puis chaque mois
   tant que la décision n'est pas prise.
5. `ln:purge-applications` ne supprime plus que les dossiers **déjà supprimés
   (soft-deleted) depuis plus de 90 jours** et ceux qu'un administrateur a
   explicitement marqués pour effacement.
6. Toute décision est journalisée dans `activity_log` avec son auteur.

`--dry-run` annonce les dossiers qui vont basculer en attente de décision.

### I.2 La contrepartie, à assumer

Ce choix a un revers qu'il faut connaître : si personne ne traite la file, des
passeports resteront stockés indéfiniment, ce qui est exactement ce que la règle
de conservation cherchait à éviter. C'est pourquoi le bandeau est permanent et le
rappel mensuel ne s'arrête jamais. Ne les rends ni masquables, ni désactivables.

### I.3 Tests attendus

1. Un dossier arrivé à échéance bascule en attente et n'est **pas** supprimé
2. « Conserver 12 mois » repousse l'échéance et retire le dossier de la file
3. La commande ne supprime aucun dossier en attente de décision
4. Le bandeau apparaît dès qu'un dossier attend, disparaît quand la file est vide

---

## Ce que tu ne dois pas faire

- Ne code aucune limite arbitraire sur le nombre de services, de questions, de
  sections, d'images ou de membres d'équipe
- Ne génère pas de classes Tailwind dynamiquement
- Ne mets aucune image de contenu sur le disque privé, ni aucun document de
  candidat sur le disque public
- N'invente ni statistique, ni témoignage, ni agrément, ni promesse de résultat
- Ne supprime aucun dossier sans décision explicite d'un administrateur
- N'écris aucun texte visible en dur : tout passe par `content()`, `setting()`
  ou les nouveaux modèles
- N'ajoute aucune dépendance sans validation
- Ne passe pas au lot suivant sans mon accord

## Critères d'acceptation

- `composer lint:check`, `composer types:check`, `php artisan test` passent
- Depuis le seul back-office, et sans toucher au code, on peut : créer un
  service avec image et le publier, ajouter une catégorie de FAQ et sept
  questions, composer une page À propos avec trois blocs dont une image, changer
  la couleur principale et voir le site s'y conformer, et remplacer le logo
- Le site reste utilisable sans JavaScript et lisible sur un écran de 360 px
- `README.md` et `DEPLOIEMENT.md` décrivent les nouvelles tables, la section
  Apparence et le nouveau fonctionnement de la conservation
