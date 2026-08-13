<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    /**
     * Show the testimonials published by the office.
     */
    public function testimonials(): View
    {
        return view('public.temoignages', [
            'temoignages' => Testimonial::published()->get(),
        ]);
    }

    /**
     * Build the sitemap from the named routes of the public site.
     */
    public function sitemap(): Response
    {
        $pages = [
            'home' => ['1.0', 'weekly'],
            'services' => ['0.9', 'monthly'],
            'a-propos' => ['0.7', 'monthly'],
            'temoignages' => ['0.7', 'weekly'],
            'faq' => ['0.7', 'monthly'],
            'contact' => ['0.8', 'monthly'],
            'depot.create' => ['1.0', 'monthly'],
            'suivi.index' => ['0.6', 'monthly'],
            'mentions-legales' => ['0.2', 'yearly'],
            'confidentialite' => ['0.2', 'yearly'],
        ];

        $urls = [];

        foreach ($pages as $name => [$priority, $frequency]) {
            if (! Route::has($name)) {
                continue;
            }

            $urls[] = [
                'loc' => route($name),
                'priority' => $priority,
                'changefreq' => $frequency,
            ];
        }

        return response()
            ->view('public.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
