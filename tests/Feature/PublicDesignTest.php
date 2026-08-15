<?php

use App\Models\Service;
use App\Models\SiteContent;
use App\Models\Testimonial;
use App\Support\SiteContentRepository;
use Database\Seeders\ContentSeeder;
use Database\Seeders\SiteContentSeeder;

beforeEach(function () {
    $this->seed(SiteContentSeeder::class);
});

/**
 * Renseigne une clé de texte comme le ferait la cliente depuis le back-office.
 */
function rediger(string $bloc, array $valeurs): void
{
    $contenu = SiteContent::query()->where('key', $bloc)->where('locale', 'fr')->firstOrFail();

    $contenu->update(['content' => array_merge($contenu->content, $valeurs)]);
}

/*
|--------------------------------------------------------------------------
| 1. Un appel à l'action dominant, partout
|--------------------------------------------------------------------------
*/

it('propose « Déposer mon dossier » sur chaque page publique', function (string $route) {
    $this->get($route)
        ->assertOk()
        ->assertSee(route('depot.create'), false)
        ->assertSee('Déposer mon dossier');
})->with([
    'accueil' => '/',
    'services' => '/services',
    'à propos' => '/a-propos',
    'témoignages' => '/temoignages',
    'faq' => '/faq',
    'contact' => '/contact',
]);

it('offre une seconde porte d\'entrée, moins engageante', function () {
    // Un candidat qui n'est pas prêt à déposer doit pouvoir poser une question
    // plutôt que de repartir.
    $this->get('/')
        ->assertOk()
        ->assertSee('Poser une question')
        ->assertSee(route('contact'), false);
});

/*
|--------------------------------------------------------------------------
| 2. Aucune statistique inventée
|--------------------------------------------------------------------------
*/

it('n\'affiche pas la bande de réassurance tant qu\'aucun chiffre réel n\'est saisi', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('Années d\'expérience')
        ->assertDontSee('Dossiers accompagnés');
});

it('affiche la bande dès qu\'un couple valeur / libellé est renseigné', function () {
    rediger('reassurance', [
        'element_1_valeur' => '12',
        'element_1_libelle' => 'Années d\'expérience',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('12')
        ->assertSee('Années d\'expérience')
        // Les emplacements restés vides n'apparaissent pas pour autant.
        ->assertDontSee('Dossiers accompagnés');
});

it('ne livre aucune promesse de résultat ni chiffre inventé', function () {
    $this->seed(ContentSeeder::class);

    $interdits = ['garanti', '% de réussite', 'taux de réussite', '100 %', 'visa assuré'];

    foreach (['/', '/services', '/a-propos', '/temoignages', '/faq', '/contact'] as $route) {
        $contenu = mb_strtolower((string) $this->get($route)->getContent());

        foreach ($interdits as $interdit) {
            expect($contenu)->not->toContain(mb_strtolower($interdit), "« {$interdit} » ne doit pas figurer sur {$route}.");
        }
    }
});

it('livre les témoignages d\'exemple non publiés et clairement marqués', function () {
    $this->seed(ContentSeeder::class);

    $exemples = Testimonial::query()->where('author_name', 'like', '%exemple%')->get();

    expect($exemples)->not->toBeEmpty()
        ->and($exemples->every(fn (Testimonial $t): bool => $t->is_published === false))->toBeTrue();

    $this->get('/temoignages')->assertOk()->assertDontSee('exemple à remplacer');
});

/*
|--------------------------------------------------------------------------
| 3. Un placeholder ne devient jamais une coordonnée cliquable
|--------------------------------------------------------------------------
*/

it('n\'affiche aucune coordonnée tant qu\'elle n\'est pas rédigée', function () {
    $contenu = (string) $this->get('/')->getContent();

    expect($contenu)->not->toContain(SiteContentRepository::PLACEHOLDER.'</a>')
        ->and($contenu)->not->toContain('href="tel:"')
        ->and($contenu)->not->toContain('href="mailto:"')
        ->and($contenu)->not->toContain('https://wa.me/"');
});

it('rend les coordonnées cliquables une fois renseignées', function () {
    rediger('global', [
        'footer_telephone' => '+237 6 99 00 00 00',
        'footer_whatsapp' => '+237 6 99 00 00 00',
        'footer_email' => 'contact@exemple.cm',
        'footer_adresse' => 'Rue de la Réunification, Douala',
        'footer_horaires' => 'Du lundi au vendredi, 8h – 17h',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('href="tel:+237699000000"', false)
        ->assertSee('href="https://wa.me/237699000000"', false)
        ->assertSee('href="mailto:contact@exemple.cm"', false)
        ->assertSee('Rue de la Réunification, Douala')
        ->assertSee('Du lundi au vendredi, 8h – 17h');
});

/*
|--------------------------------------------------------------------------
| 4. Le statut professionnel : affiché s'il est déclaré, jamais supposé
|--------------------------------------------------------------------------
*/

it('masque le statut professionnel tant qu\'il n\'est pas rédigé', function () {
    $this->get('/a-propos')
        ->assertOk()
        ->assertDontSee('À quel titre nous intervenons');
});

it('affiche le statut professionnel et son numéro une fois déclarés', function () {
    rediger('a_propos', [
        'statut_texte' => 'Cabinet de conseil en immigration établi à Douala.',
        'statut_numero' => 'R000000',
    ]);

    $this->get('/a-propos')
        ->assertOk()
        ->assertSee('À quel titre nous intervenons')
        ->assertSee('Cabinet de conseil en immigration établi à Douala.')
        ->assertSee('R000000');
});

/*
|--------------------------------------------------------------------------
| 5. Témoignages : prénom, pays et programme
|--------------------------------------------------------------------------
*/

it('affiche le pays et le programme obtenu avec le témoignage', function () {
    Testimonial::factory()->create([
        'author_name' => 'Aminata',
        'author_country' => 'Cameroun',
        'author_programme' => 'Entrée Express, 2025',
        'content' => 'Un accompagnement clair du début à la fin.',
        'is_published' => true,
    ]);

    foreach (['/', '/temoignages'] as $route) {
        $this->get($route)
            ->assertOk()
            ->assertSee('Aminata')
            ->assertSee('Cameroun')
            ->assertSee('Entrée Express, 2025');
    }
});

/*
|--------------------------------------------------------------------------
| 6. Périmètre explicite sur la fiche d'un service
|--------------------------------------------------------------------------
*/

it('affiche ce qui est compris et ce qui ne l\'est pas', function () {
    $service = Service::factory()->published()->create([
        'slug' => 'entree-express',
        'price_note' => 'Sur devis, après évaluation du profil',
        'included' => ['Évaluation complète du profil', 'Constitution du dossier'],
        'excluded' => ['Frais gouvernementaux', 'Traduction certifiée'],
    ]);

    $this->get(route('services.show', $service))
        ->assertOk()
        ->assertSee('Compris dans la prestation')
        ->assertSee('Évaluation complète du profil')
        ->assertSee('Non compris')
        ->assertSee('Frais gouvernementaux')
        ->assertSee('Sur devis, après évaluation du profil');
});

it('n\'affiche aucun encadré de périmètre si rien n\'est renseigné', function () {
    $service = Service::factory()->published()->create([
        'slug' => 'permis-de-travail',
        'included' => null,
        'excluded' => null,
    ]);

    $this->get(route('services.show', $service))
        ->assertOk()
        ->assertDontSee('Compris dans la prestation');
});

/*
|--------------------------------------------------------------------------
| 7. Le processus suit les textes, sans nombre d'étapes figé
|--------------------------------------------------------------------------
*/

it('n\'affiche que les étapes réellement rédigées', function () {
    rediger('accueil', [
        'etape_1_titre' => 'Nous évaluons votre profil',
        'etape_2_titre' => 'Vous déposez votre dossier',
        'etape_3_titre' => SiteContentRepository::PLACEHOLDER,
        'etape_4_titre' => SiteContentRepository::PLACEHOLDER,
    ]);

    $contenu = (string) $this->get('/')->assertOk()->getContent();

    // On isole la liste des étapes : le placeholder figure légitimement
    // ailleurs sur la page, sur les textes que la cliente n'a pas encore
    // rédigés. Ce qu'on vérifie ici, c'est qu'aucune carte d'étape vide n'est
    // rendue.
    preg_match('#<ol[^>]*>.*?</ol>#s', $contenu, $trouve);

    expect($trouve)->not->toBeEmpty();

    expect($trouve[0])->toContain('Nous évaluons votre profil')
        ->and($trouve[0])->toContain('Vous déposez votre dossier')
        ->and($trouve[0])->not->toContain(SiteContentRepository::PLACEHOLDER)
        ->and(substr_count($trouve[0], '<li'))->toBe(2);
});

it('prend en compte une cinquième étape ajoutée depuis le back-office', function () {
    rediger('accueil', [
        'etape_5_titre' => 'Nous préparons votre installation',
        'etape_5_texte' => 'Logement, banque, premières démarches.',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Nous préparons votre installation')
        ->assertSee('Logement, banque, premières démarches.');
});

/*
|--------------------------------------------------------------------------
| 8. Ce que la refonte ne devait pas toucher (§H.4)
|--------------------------------------------------------------------------
*/

it('laisse le dépôt et le suivi intacts', function () {
    $this->get(route('depot.create'))
        ->assertOk()
        ->assertSee('name="_token"', false);

    $this->get(route('suivi.index'))->assertOk();
});

it('fonctionne sans JavaScript sur les pages refondues', function (string $route) {
    $contenu = (string) $this->get($route)->assertOk()->getContent();

    // Le menu mobile et l'accordéon reposent sur <details>, pas sur un script :
    // une page vitrine doit rester lisible quand le JavaScript ne charge pas.
    expect($contenu)->toContain('<details');
})->with([
    'accueil' => '/',
    'services' => '/services',
    'à propos' => '/a-propos',
]);
