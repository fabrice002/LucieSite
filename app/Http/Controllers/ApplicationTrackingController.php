<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackApplicationRequest;
use App\Models\Application;
use Illuminate\View\View;

class ApplicationTrackingController extends Controller
{
    /**
     * Show the tracking form.
     */
    public function index(): View
    {
        return view('public.suivi');
    }

    /**
     * Look up an application from its reference and email address.
     *
     * Seuls le statut et la date de mise à jour sont exposés : aucun document,
     * aucune note interne. Une référence inconnue et une référence dont l'e-mail
     * ne correspond pas produisent exactement la même réponse.
     */
    public function show(TrackApplicationRequest $request): View
    {
        $application = Application::query()
            ->where('reference', $request->string('reference')->trim()->upper()->value())
            ->whereRaw('LOWER(email) = ?', [$request->string('email')->trim()->lower()->value()])
            ->first();

        return view('public.suivi', [
            'searched' => true,
            'status' => $application?->status,
            'updatedAt' => $application?->updated_at,
        ]);
    }
}
