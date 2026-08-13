<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crée les deux rôles du back-office.
 *
 * `admin` a tous les droits. `agent` consulte les dossiers et change leur
 * statut, mais ne supprime rien et ne touche pas aux textes du site.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'agent'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
