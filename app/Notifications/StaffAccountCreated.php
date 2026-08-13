<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient un membre du cabinet que son compte vient d'être créé.
 *
 * Le mot de passe provisoire n'est volontairement PAS repris ici : un e-mail
 * traverse trop de serveurs pour transporter un identifiant. Il se communique
 * de vive voix ou par un canal sûr.
 */
class StaffAccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $user, public readonly string $role) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre accès au back-office '.config('app.name'))
            ->greeting('Bonjour '.$this->user->name.',')
            ->line('Un compte vient d\'être créé pour vous sur le back-office de '.config('app.name').'.')
            ->line('Votre identifiant est votre adresse e-mail : **'.$this->user->email.'**')
            ->line('Rôle attribué : **'.$this->role.'**')
            ->line('Le mot de passe provisoire vous est communiqué séparément. À votre première connexion, il vous sera demandé d\'en choisir un nouveau.')
            ->action('Accéder au back-office', url('/admin'))
            ->line('Ce back-office donne accès à des pièces d\'identité de candidats : activez la double authentification depuis la page Sécurité.')
            ->salutation('L\'équipe '.config('app.name'));
    }
}
