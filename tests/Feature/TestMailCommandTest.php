<?php

use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail;

it('envoie un message à l\'adresse indiquée', function () {
    Mail::fake();

    $this->artisan('ln:test-mail', ['adresse' => 'lucie@cabinet.cm'])
        ->assertSuccessful();

    Mail::assertSent(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo('lucie@cabinet.cm'));
});

it('n\'envoie pas et se plaint si l\'adresse est invalide', function () {
    Mail::fake();

    $this->artisan('ln:test-mail', ['adresse' => 'pas-une-adresse'])
        ->expectsOutputToContain("n'est pas une adresse e-mail valide")
        ->assertFailed();

    Mail::assertNothingSent();
});

it('affiche la configuration utilisée', function () {
    Mail::fake();

    config([
        'mail.mailers.smtp.host' => 'smtp.gmail.com',
        'mail.from.address' => 'contact@cabinet.cm',
    ]);

    $this->artisan('ln:test-mail', ['adresse' => 'lucie@cabinet.cm'])
        ->expectsOutputToContain('smtp.gmail.com')
        ->expectsOutputToContain('contact@cabinet.cm')
        ->assertSuccessful();
});

it('avertit que le sandbox Mailtrap ne délivre jamais vraiment', function () {
    Mail::fake();

    config(['mail.mailers.smtp.host' => 'sandbox.smtp.mailtrap.io']);

    $this->artisan('ln:test-mail', ['adresse' => 'lucie@cabinet.cm'])
        ->expectsOutputToContain('sandbox Mailtrap ne délivre jamais')
        ->assertSuccessful();
});

it('avertit que le transport log n\'envoie rien', function () {
    Mail::fake();

    config(['mail.default' => 'log']);

    $this->artisan('ln:test-mail', ['adresse' => 'lucie@cabinet.cm'])
        ->expectsOutputToContain('rien ne partira')
        ->assertSuccessful();
});

it('traduit une erreur SMTP en action concrète', function () {
    // On simule le refus exact que renvoie Gmail devant un mot de passe de compte.
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException(
        'Failed to authenticate on SMTP server. 534-5.7.9 Application-specific password required.',
    ));

    $this->artisan('ln:test-mail', ['adresse' => 'lucie@cabinet.cm'])
        ->expectsOutputToContain('Application-specific password required')
        ->expectsOutputToContain('myaccount.google.com/apppasswords')
        ->assertFailed();
});
