<?php

use App\Actions\SubmitApplication;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake(SubmitApplication::DISK);

    $this->document = Document::factory()->create(['path' => 'documents/LN-2026-00001/secret.pdf']);

    Storage::disk(SubmitApplication::DISK)->put($this->document->path, 'contenu confidentiel');
});

it('ne sert jamais le fichier à un visiteur non authentifié', function () {
    $response = $this->get(route('documents.download', $this->document));

    $response->assertRedirect(route('login'));
    expect($response->getContent())->not->toContain('contenu confidentiel');
});

it('refuse un utilisateur authentifié sans rôle', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('documents.download', $this->document));

    $response->assertForbidden();
    expect($response->getContent())->not->toContain('contenu confidentiel');
});

it('autorise un agent du cabinet', function () {
    Role::findOrCreate('agent');

    $user = User::factory()->create();
    $user->assignRole('agent');

    $response = $this->actingAs($user)->get(route('documents.download', $this->document));

    $response->assertOk();
    $response->assertDownload($this->document->original_name);
});

it('ne divulgue pas les documents par une URL directe sur le disque privé', function () {
    // La route storage/{path} du framework exige une signature valide.
    $response = $this->get('/storage/'.$this->document->path);

    expect($response->status())->toBeIn([403, 404]);
    expect($response->getContent())->not->toContain('contenu confidentiel');
});
