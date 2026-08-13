<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\StaffAccountCreated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Crée un compte du cabinet.
 *
 * L'inscription publique est fermée : c'est le seul moyen de créer un compte,
 * avec le seeder.
 */
class CreateStaffUser extends Command
{
    protected $signature = 'ln:create-user
                            {--name= : Nom affiché}
                            {--email= : Adresse e-mail}
                            {--role=admin : Rôle attribué (admin ou agent)}
                            {--password= : Mot de passe provisoire, sinon demandé ou généré}
                            {--generate : Génère un mot de passe provisoire et l\'affiche}';

    protected $description = 'Crée un compte administrateur ou agent pour le back-office';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Nom', required: true);
        $email = $this->option('email') ?: text('Adresse e-mail', required: true);
        $role = $this->option('role') ?: select('Rôle', ['admin', 'agent'], 'admin');

        if (! in_array($role, ['admin', 'agent'], true)) {
            $this->error('Le rôle doit être « admin » ou « agent ».');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error("Un compte existe déjà avec l'adresse {$email}.");

            return self::FAILURE;
        }

        // En déploiement scripté il n'y a pas de terminal interactif : le mot de
        // passe se fournit alors en option, ou se génère.
        $genere = false;

        if (is_string($this->option('password')) && $this->option('password') !== '') {
            $plain = (string) $this->option('password');
        } elseif ($this->option('generate')) {
            $plain = Str::password(16);
            $genere = true;
        } else {
            $plain = password('Mot de passe', required: true);
        }

        try {
            validator(
                ['password' => $plain],
                ['password' => ['required', 'string', Password::default()]],
            )->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        Role::findOrCreate($role, 'web');

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plain),
        ]);

        // Le compte est créé par une personne de confiance en console : inutile
        // de lui demander de vérifier son adresse. En revanche le mot de passe
        // est provisoire, il devra être remplacé à la première connexion.
        $user->forceFill([
            'email_verified_at' => now(),
            'must_change_password' => true,
        ])->save();

        $user->assignRole($role);

        $user->notify(new StaffAccountCreated($user, $role));

        $this->info("Compte {$role} créé pour {$email}.");

        if ($genere) {
            $this->newLine();
            $this->line('  Mot de passe provisoire : <fg=yellow;options=bold>'.$plain.'</>');
            $this->line('  À communiquer de vive voix ou par un canal sûr, jamais par e-mail.');
            $this->newLine();
        }

        $this->line('Connexion sur '.url('/admin'));
        $this->line('Un e-mail de bienvenue part en file d\'attente : le worker doit tourner.');
        $this->comment('Le mot de passe est provisoire, il sera demandé de le changer à la première connexion.');

        return self::SUCCESS;
    }
}
