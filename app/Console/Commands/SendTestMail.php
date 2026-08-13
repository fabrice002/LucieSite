<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoie un e-mail de test, sans passer par la file d'attente.
 *
 * Le circuit normal met les notifications en file : quand rien n'arrive, on ne
 * sait pas si le problème vient de la configuration SMTP ou d'un worker à
 * l'arrêt. Cette commande envoie immédiatement et affiche l'erreur telle que
 * le serveur SMTP l'a renvoyée, ce qui tranche la question tout de suite.
 */
class SendTestMail extends Command
{
    protected $signature = 'ln:test-mail {adresse : Adresse qui doit recevoir le message}';

    protected $description = 'Envoie un e-mail de test immédiatement, sans file d\'attente';

    public function handle(): int
    {
        $adresse = (string) $this->argument('adresse');

        if (! filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
            $this->error("« {$adresse} » n'est pas une adresse e-mail valide.");

            return self::FAILURE;
        }

        $this->afficherConfiguration();

        $this->newLine();
        $this->line("Envoi en cours vers {$adresse}…");

        try {
            // Envoi synchrone : c'est tout l'intérêt de cette commande.
            Mail::to($adresse)->send(new TestMail);
        } catch (Throwable $exception) {
            $this->newLine();
            $this->error('L\'envoi a échoué.');
            $this->newLine();
            $this->line('<fg=red>'.$exception->getMessage().'</>');

            $this->indice($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Message envoyé.');
        $this->line("Vérifiez la boîte de réception de {$adresse}, et les indésirables.");
        $this->newLine();
        $this->comment('La configuration SMTP est donc bonne. Si les e-mails du site');
        $this->comment('n\'arrivent toujours pas, le problème vient du worker :');
        $this->comment('  php artisan queue:work');

        return self::SUCCESS;
    }

    private function afficherConfiguration(): void
    {
        $identifiant = config('mail.mailers.smtp.username');

        $this->line('<options=bold>Configuration utilisée</>');

        $this->table(['Réglage', 'Valeur'], [
            ['Transport', (string) config('mail.default')],
            ['Hôte', (string) config('mail.mailers.smtp.host')],
            ['Port', (string) config('mail.mailers.smtp.port')],
            ['Chiffrement', (string) (config('mail.mailers.smtp.scheme') ?: 'automatique')],
            ['Identifiant', is_string($identifiant) && $identifiant !== ''
                ? substr($identifiant, 0, 4).'…'
                : '(vide)'],
            ['Mot de passe', filled(config('mail.mailers.smtp.password')) ? 'défini' : '(vide)'],
            ['Expéditeur', (string) config('mail.from.address')],
            ['Administratrice', (string) (config('mail.admin_address') ?: '(non renseignée)')],
        ]);

        if (config('mail.default') === 'log') {
            $this->warn('MAIL_MAILER=log : rien ne partira, le message ira dans storage/logs.');
        }

        if (str_contains((string) config('mail.mailers.smtp.host'), 'sandbox')) {
            $this->warn('Le sandbox Mailtrap ne délivre jamais vers une vraie boîte.');
        }
    }

    /**
     * Traduit les erreurs SMTP les plus fréquentes en action concrète.
     */
    private function indice(string $erreur): void
    {
        $indices = [
            'Application-specific password required' => [
                'Gmail refuse le mot de passe du compte.',
                'Générez un mot de passe d\'application sur https://myaccount.google.com/apppasswords',
                'puis collez ses 16 caractères, sans espaces, dans MAIL_PASSWORD.',
            ],
            'Username and Password not accepted' => [
                'Identifiant ou mot de passe refusé par le serveur.',
                'Vérifiez MAIL_USERNAME et MAIL_PASSWORD.',
            ],
            'Connection could not be established' => [
                'Le serveur SMTP est injoignable.',
                'Vérifiez MAIL_HOST et MAIL_PORT, ainsi que votre pare-feu.',
            ],
            'Failed to authenticate' => [
                'L\'authentification a échoué.',
                'Chez la plupart des fournisseurs, le mot de passe SMTP diffère du mot de passe du compte.',
            ],
            'sender address' => [
                'L\'expéditeur est refusé.',
                'MAIL_FROM_ADDRESS doit être une adresse autorisée par le serveur SMTP.',
            ],
        ];

        foreach ($indices as $motif => $lignes) {
            if (str_contains($erreur, $motif)) {
                $this->newLine();
                $this->line('<options=bold>Ce qu\'il faut faire</>');

                foreach ($lignes as $ligne) {
                    $this->line('  '.$ligne);
                }

                return;
            }
        }

        $this->newLine();
        $this->comment('Pensez à lancer « php artisan config:clear » après avoir modifié .env.');
    }
}
