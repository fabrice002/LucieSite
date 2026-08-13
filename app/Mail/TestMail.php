<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Message de vérification de la configuration SMTP.
 *
 * Volontairement pas ShouldQueue : la commande ln:test-mail doit envoyer
 * immédiatement, pour distinguer un problème de configuration d'un worker
 * à l'arrêt.
 */
class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test d\'envoi — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.test',
            with: ['envoyeLe' => now()->translatedFormat('j F Y à H:i')],
        );
    }
}
