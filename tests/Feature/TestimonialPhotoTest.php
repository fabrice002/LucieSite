<?php

use App\Models\Testimonial;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(SiteContentSeeder::class);
});

it('construit une URL de photo indépendante de l\'hôte', function () {
    // Construite depuis APP_URL, l'URL casserait dès que le site est consulté
    // sur un autre hôte ou un autre port — depuis un téléphone, par exemple.
    config(['app.url' => 'http://un-autre-hote.test:9999']);

    $url = Storage::disk('public')->url('temoignages/photo.jpg');

    expect($url)->toBe('/storage/temoignages/photo.jpg')
        ->and($url)->not->toContain('un-autre-hote');
});

it('affiche la photo d\'un témoignage publié', function () {
    Storage::fake('public');

    $photo = UploadedFile::fake()->image('aicha.jpg')->store('temoignages', 'public');

    Testimonial::factory()->published()->create([
        'author_name' => 'Aïcha Nkolo',
        'photo_path' => $photo,
    ]);

    $this->get(route('temoignages'))
        ->assertOk()
        ->assertSee('Aïcha Nkolo')
        ->assertSee(Storage::disk('public')->url($photo), false);
});

it('remplace la photo manquante par les initiales de l\'auteur', function () {
    Testimonial::factory()->published()->create([
        'author_name' => 'Brice Kamga',
        'photo_path' => null,
    ]);

    $response = $this->get(route('temoignages'));

    $response->assertOk()->assertSee('Brice Kamga');

    // Aucune balise image cassée : l'initiale de l'auteur prend la place.
    expect($response->getContent())
        ->not->toContain('<img src="/storage')
        ->and(preg_match('/rounded-full[^"]*"[^>]*>\s*B\s*</', $response->getContent()))->toBe(1);
});

it('range la photo sur le disque public et rien d\'autre', function () {
    Storage::fake('public');
    Storage::fake('local');

    $photo = UploadedFile::fake()->image('portrait.jpg')->store('temoignages', 'public');

    Storage::disk('public')->assertExists($photo);
    Storage::disk('local')->assertMissing($photo);

    // Le disque public ne doit jamais héberger de pièce de candidat.
    expect(Storage::disk('public')->allDirectories())->toBe(['temoignages']);
});
