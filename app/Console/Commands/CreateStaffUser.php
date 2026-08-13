<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
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
                            {--role=admin : Rôle attribué (admin ou agent)}';

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

        $plain = password('Mot de passe', required: true);

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
        // de lui demander de vérifier son adresse.
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($role);

        $this->info("Compte {$role} créé pour {$email}.");
        $this->line('Connexion sur '.url('/admin'));

        return self::SUCCESS;
    }
}
