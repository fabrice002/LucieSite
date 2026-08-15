<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pose ou retire le jeu de démonstration.
 *
 * Refuse de tourner en production : ce jeu contient des témoignages et des
 * chiffres fictifs, qui n'ont rien à faire sur un site en service.
 */
class SeedDemoData extends Command
{
    protected $signature = 'ln:demo
        {--fresh : Repart d\'une base vierge avant de remplir}
        {--purge : Retire le jeu de démonstration au lieu de le poser}';

    protected $description = 'Remplit le site avec un jeu de démonstration complet, pour tester sur écran et sur téléphone';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusé : cette commande ne doit jamais tourner en production.');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            return $this->purger();
        }

        if ($this->option('fresh') && ! $this->confirm('Toute la base va être effacée puis reconstruite. Continuer ?', true)) {
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh');

            // migrate:fresh vide les tables, pas le disque. Sans ce nettoyage,
            // les pièces du jeu précédent resteraient sur storage/app/private
            // sans plus aucune ligne pour les référencer : des scans orphelins,
            // que plus rien ne viendrait jamais effacer.
            Storage::disk('local')->deleteDirectory('documents');
            $this->line('Pièces du jeu précédent retirées du disque privé.');
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->call('cache:clear');

        $this->newLine();
        $this->info('Jeu de démonstration en place.');
        $this->line('  Back-office : '.url('/admin'));
        $this->line('  Connexion   : admin@demo.test — motdepasse');
        $this->instructionsAndroid();

        $this->newLine();
        $this->line('Pour tout retirer : php artisan ln:demo --purge');

        return self::SUCCESS;
    }

    /**
     * Comment ouvrir le site depuis un téléphone du même réseau.
     *
     * Les candidats déposent leur dossier depuis un Android en 3G : c'est la
     * seule façon de voir réellement ce que le site leur montre.
     */
    private function instructionsAndroid(): void
    {
        $ip = $this->adresseReseau();
        $port = 8000;

        $this->newLine();
        $this->comment('Tester depuis un téléphone Android, sur le même réseau Wi-Fi :');
        $this->newLine();

        $this->line("  1. php artisan serve --host=0.0.0.0 --port={$port}");

        if ($ip !== null) {
            $this->line("  2. Dans .env : APP_URL=http://{$ip}:{$port}");
            $this->line('     puis php artisan config:clear && npm run build');
            $this->line("  3. Sur le téléphone : http://{$ip}:{$port}");
        } else {
            $this->line('  2. Relevez l\'adresse IP de ce poste (ipconfig), et mettez-la dans APP_URL');
            $this->line('     puis php artisan config:clear && npm run build');
            $this->line('  3. Ouvrez cette adresse sur le téléphone, port '.$port);
        }

        $this->newLine();
        $this->line('  APP_URL compte : les liens et les fichiers compilés en sont dérivés.');
        $this->line('  Laissé sur localhost, la page s\'ouvre mais arrive sans style ni JavaScript.');
        $this->newLine();
        $this->line('  Pour simuler la 3G : Chrome Android → chrome://inspect depuis le poste,');
        $this->line('  ou les outils de développement → Network → Slow 3G.');
    }

    /**
     * L'adresse IPv4 de ce poste sur le réseau local, si on peut la connaître.
     *
     * On ouvre une connexion UDP — qui n'envoie rien — uniquement pour laisser
     * le système choisir l'interface qu'il utiliserait vers l'extérieur.
     */
    private function adresseReseau(): ?string
    {
        $socket = @stream_socket_client('udp://8.8.8.8:53', $erreur, $message, 1);

        if ($socket === false) {
            return null;
        }

        $nom = stream_socket_get_name($socket, false);
        fclose($socket);

        if (! is_string($nom) || ! str_contains($nom, ':')) {
            return null;
        }

        $ip = Str::before($nom, ':');

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? $ip : null;
    }

    /**
     * Retire ce que le seeder a créé, et lui seul.
     */
    private function purger(): int
    {
        if (! $this->confirm('Retirer tout le contenu de démonstration ?', true)) {
            return self::FAILURE;
        }

        $marque = '%'.DemoSeeder::MARQUE.'%';

        // Les dossiers d'abord : leurs pièces sont sur le disque privé.
        $dossiers = Application::withTrashed()->where('email', 'like', '%@demo.test')->get();

        foreach ($dossiers as $dossier) {
            Storage::disk('local')->deleteDirectory('documents/'.$dossier->reference);
            $dossier->forceDelete();
        }

        Testimonial::query()->where('author_name', 'like', $marque)->delete();
        TeamMember::query()->where('bio', 'like', $marque)->delete();
        User::query()->where('email', 'like', '%@demo.test')->delete();

        // Contenus éditoriaux : on les dépublie plutôt que de les détruire, la
        // cliente ayant pu commencer à les retravailler.
        Service::query()->update(['is_published' => false]);
        PageSection::query()->update(['is_published' => false]);
        FaqCategory::query()->update(['is_published' => false]);
        Faq::query()->update(['is_published' => false]);

        SiteSetting::query()->delete();

        $this->call('cache:clear');

        $this->info($dossiers->count().' dossier(s) et leurs pièces effacés.');
        $this->line('Contenus éditoriaux dépubliés, réglages d\'apparence remis à leur valeur livrée.');
        $this->comment('Les textes du site gardent leur contenu de démonstration : relancez');
        $this->comment('SiteContentSeeder sur une base vierge pour repartir des placeholders.');

        return self::SUCCESS;
    }
}
