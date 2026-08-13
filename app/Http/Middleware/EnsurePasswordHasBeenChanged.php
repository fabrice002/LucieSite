<?php

namespace App\Http\Middleware;

use App\Filament\Pages\ChangerMotDePasse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque le back-office tant qu'un mot de passe provisoire n'a pas été changé.
 *
 * Un compte créé par un administrateur démarre avec un mot de passe que deux
 * personnes connaissent. Tant qu'il n'a pas été remplacé, toute page du panel
 * ramène à l'écran de changement.
 */
class EnsurePasswordHasBeenChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        $destination = ChangerMotDePasse::getUrl();
        $chemin = parse_url($destination, PHP_URL_PATH);

        // On laisse passer la page de changement elle-même, sinon la
        // redirection tournerait en boucle. Et la déconnexion, pour ne
        // jamais enfermer quelqu'un dans le panel.
        if ((is_string($chemin) && $request->is(ltrim($chemin, '/')))
            || $request->routeIs('filament.admin.auth.logout')
            || $request->routeIs('*logout')) {
            return $next($request);
        }

        // Livewire met à jour la page en arrière-plan : on ne l'interrompt pas,
        // sinon le formulaire de changement cesserait de fonctionner.
        if ($request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        return redirect()->to($destination);
    }
}
