<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationUpdate;
use App\Models\User;
use App\Notifications\ApplicationStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Informe un candidat de l'avancement de son dossier.
 *
 * Deux canaux, volontairement séparés :
 *   — l'e-mail n'est qu'une alerte : référence, nouveau statut, et une
 *     invitation à consulter la page de suivi ;
 *   — le message rédigé par le cabinet ne se lit que sur la plateforme, après
 *     vérification de la référence et de l'adresse e-mail.
 *
 * Une boîte e-mail peut être partagée, consultée sur un téléphone prêté ou
 * compromise : le contenu sensible reste derrière cette vérification.
 */
class NotifyApplicant
{
    public function handle(
        Application $application,
        ?ApplicationStatus $status,
        ?string $publicMessage,
        bool $sendEmail,
        User $author,
    ): ApplicationUpdate {
        $publicMessage = $this->normaliser($publicMessage);

        throw_if(
            $status === null && $publicMessage === null,
            new InvalidArgumentException('Une mise à jour doit porter un statut, un message, ou les deux.'),
        );

        $update = DB::transaction(function () use ($application, $status, $publicMessage, $sendEmail, $author): ApplicationUpdate {
            // applications.status reste la source de vérité du statut courant.
            if ($status !== null && $status !== $application->status) {
                $application->update(['status' => $status]);
            }

            $update = $application->updates()->create([
                'user_id' => $author->getKey(),
                'status' => $status,
                'public_message' => $publicMessage,
                'email_sent' => $sendEmail,
                'emailed_at' => $sendEmail ? now() : null,
            ]);

            // Journalisé sur le dossier, pour apparaître dans son historique.
            // On note qu'un message a été écrit, jamais son contenu : le journal
            // est consultable plus largement que la fiche elle-même.
            activity('dossier')
                ->performedOn($application)
                ->causedBy($author)
                ->event('informed')
                ->withProperties([
                    'statut' => $status?->value,
                    'message' => $publicMessage !== null,
                    'email' => $sendEmail,
                ])
                ->log('Candidat informé');

            return $update;
        });

        if ($sendEmail) {
            // Après le commit : sans cela, un worker rapide pourrait traiter le
            // job avant que la transaction ne soit visible en base.
            DB::afterCommit(fn () => Notification::route('mail', $application->email)
                ->notify(new ApplicationStatusChanged($application->refresh())));
        }

        return $update;
    }

    /**
     * Un message composé uniquement d'espaces équivaut à pas de message.
     */
    private function normaliser(?string $message): ?string
    {
        $message = trim((string) $message);

        return $message === '' ? null : $message;
    }
}
