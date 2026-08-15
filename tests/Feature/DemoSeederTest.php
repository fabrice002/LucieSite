<?php

use App\Actions\PurgeExpiredApplications;
use App\Filament\Widgets\PendingRetentionBanner;
use App\Models\Application;
use App\Models\Document;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\SiteContentRepository;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->seed(DemoSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Le site est réellement complet
|--------------------------------------------------------------------------
*/

it('rend chaque page publique sans placeholder restant', function (string $route) {
    $contenu = (string) $this->get($route)->assertOk()->getContent();

    // Le but du jeu de démonstration : voir le site tel qu'il sera, sans
    // « [À COMPLÉTER PAR LA CLIENTE] » en travers de chaque paragraphe.
    expect($contenu)->not->toContain(SiteContentRepository::PLACEHOLDER);
})->with([
    'accueil' => '/',
    'services' => '/services',
    'fiche service' => '/services/entree-express',
    'faq' => '/faq',
    'à propos' => '/a-propos',
    'témoignages' => '/temoignages',
    'contact' => '/contact',
    'dépôt' => '/deposer-mon-dossier',
    'suivi' => '/suivre-mon-dossier',
    'plan du site' => '/sitemap.xml',
]);

it('laisse les mentions juridiques à compléter, même en démonstration', function (string $route) {
    // Raison sociale, immatriculation, hébergeur, autorité de contrôle : en
    // inventer, fût-ce pour une démonstration, c'est risquer qu'elles partent
    // en ligne telles quelles. Le placeholder est ici le comportement voulu.
    $this->get($route)->assertOk()->assertSee(SiteContentRepository::PLACEHOLDER);
})->with([
    'mentions légales' => '/mentions-legales',
    'confidentialité' => '/politique-de-confidentialite',
]);

it('publie du contenu dans chaque rubrique', function () {
    expect(Service::query()->where('is_published', true)->count())->toBeGreaterThanOrEqual(6)
        ->and(FaqCategory::query()->where('is_published', true)->count())->toBeGreaterThanOrEqual(4)
        ->and(Faq::query()->where('is_published', true)->count())->toBeGreaterThanOrEqual(15)
        ->and(PageSection::query()->where('is_published', true)->count())->toBeGreaterThanOrEqual(6)
        ->and(TeamMember::query()->where('is_published', true)->count())->toBeGreaterThanOrEqual(4)
        ->and(Testimonial::query()->where('is_published', true)->count())->toBeGreaterThanOrEqual(5)
        ->and(SiteSetting::query()->count())->toBeGreaterThanOrEqual(6);
});

it('affiche la bande de réassurance et le périmètre des services', function () {
    $this->get('/')->assertOk()->assertSee('Le cabinet en bref');

    $this->get('/services/entree-express')
        ->assertOk()
        ->assertSee('Compris dans la prestation')
        ->assertSee('Non compris')
        ->assertSee('Frais gouvernementaux et frais de visa');
});

/*
|--------------------------------------------------------------------------
| Les dossiers existent vraiment, fichiers compris
|--------------------------------------------------------------------------
*/

it('crée des dossiers dans chaque statut, avec leurs pièces sur le disque', function () {
    expect(Application::withTrashed()->count())->toBeGreaterThanOrEqual(20)
        ->and(Document::query()->count())->toBeGreaterThanOrEqual(60);

    // Un chemin en base sans fichier derrière donnerait un back-office qui a
    // l'air de marcher jusqu'au premier téléchargement.
    foreach (Document::query()->limit(10)->get() as $document) {
        Storage::disk('local')->assertExists($document->path);
    }
});

it('garnit la file de conservation, pour que le bandeau soit visible', function () {
    expect(PurgeExpiredApplications::enAttenteDeDecision()->count())->toBeGreaterThanOrEqual(1);

    $admin = User::query()->where('email', 'admin@demo.test')->sole();

    $this->actingAs($admin);

    expect(PendingRetentionBanner::canView())->toBeTrue();
});

it('ouvre le back-office avec les comptes de démonstration', function (string $email) {
    $compte = User::query()->where('email', $email)->sole();

    $this->actingAs($compte)->get('/admin')->assertOk();
})->with([
    'admin' => 'admin@demo.test',
    'agent' => 'agent@demo.test',
]);

/*
|--------------------------------------------------------------------------
| Rien ne peut être confondu avec du contenu réel
|--------------------------------------------------------------------------
*/

it('marque tout ce qui est inventé', function () {
    // Témoignages et chiffres sont exactement ce que le cahier des charges
    // interdit d'inventer. Ils sont tolérés ici parce qu'ils sont signés.
    $temoignages = Testimonial::query()->get();

    expect($temoignages)->not->toBeEmpty();

    foreach ($temoignages as $temoignage) {
        expect($temoignage->author_name)->toContain(DemoSeeder::MARQUE);
    }

    // Les chiffres de la bande de réassurance également.
    $this->get('/')->assertOk()->assertSee(DemoSeeder::MARQUE);
});

it('ne laisse aucun dossier derrière lui au retrait', function () {
    // Le retrait retrouve les dossiers par leur adresse. Un dossier créé sans
    // adresse en @demo.test resterait en base, ses scans avec — invisible, et
    // plus rien pour l'effacer.
    expect(Application::withTrashed()->count())->toBeGreaterThan(0)
        ->and(Application::withTrashed()->where('email', 'not like', '%@demo.test')->count())->toBe(0);

    $this->artisan('ln:demo --purge')->expectsConfirmation('Retirer tout le contenu de démonstration ?', 'yes')
        ->assertSuccessful();

    expect(Application::withTrashed()->count())->toBe(0)
        ->and(Document::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('documents'))->toBeEmpty()
        ->and(User::query()->where('email', 'like', '%@demo.test')->count())->toBe(0)
        ->and(Testimonial::query()->count())->toBe(0);
});

it('refuse de tourner en production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    // Appel direct : passer par $this->seed() ouvrirait la confirmation
    // interactive de la console avant même d'atteindre le garde-fou.
    expect(fn () => app(DemoSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'refuse de tourner en production');
});
