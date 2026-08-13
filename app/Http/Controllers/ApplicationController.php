<?php

namespace App\Http\Controllers;

use App\Actions\SubmitApplication;
use App\Http\Requests\StoreApplicationRequest;
use App\Support\TemporaryUploadStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    /**
     * Show the submission form.
     */
    public function create(): View
    {
        return view('public.depot');
    }

    /**
     * Persist a new application and redirect to the confirmation page.
     */
    public function store(
        StoreApplicationRequest $request,
        SubmitApplication $submit,
        TemporaryUploadStorage $uploads,
    ): RedirectResponse {
        $application = $submit(
            $request->candidateAttributes(),
            $request->documents(),
            $request->ip(),
        );

        foreach ($request->consumedTokens() as $token) {
            $uploads->forget($token);
        }

        // La référence transite par la session : elle n'apparaît pas dans l'URL.
        Session::flash('reference', $application->reference);

        return redirect()->route('depot.confirmation');
    }

    /**
     * Show the confirmation page carrying the tracking reference.
     */
    public function confirmation(): View|RedirectResponse
    {
        $reference = Session::get('reference');

        if (! is_string($reference)) {
            return redirect()->route('depot.create');
        }

        return view('public.confirmation', ['reference' => $reference]);
    }
}
