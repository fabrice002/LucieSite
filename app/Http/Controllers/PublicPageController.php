<?php

namespace App\Http\Controllers;

use App\Models\FaqCategory;
use App\Models\Service;
use App\Models\Testimonial;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    /**
     * Show the published services.
     */
    public function services(): View
    {
        return view('public.services', ['services' => Service::publiés()]);
    }

    /**
     * Show a single service.
     *
     * Un service dépublié n'existe pas pour le public : sa page renvoie 404,
     * et non une page vide qui laisserait croire à une erreur passagère.
     */
    public function service(Service $service): View
    {
        abort_unless($service->is_published, 404);

        return view('public.service', ['service' => $service]);
    }

    /**
     * Show the frequently asked questions, grouped by theme.
     */
    public function faq(): View
    {
        return view('public.faq', ['categories' => FaqCategory::avecQuestions()]);
    }

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
     * Build the sitemap from the public pages and the published services.
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

        // Chaque service publié a sa page, référencée séparément : c'est ce qui
        // apporte le trafic organique. Les services dépubliés en sont absents.
        foreach (Service::publiés() as $service) {
            $urls[] = [
                'loc' => route('services.show', $service),
                'priority' => '0.8',
                'changefreq' => 'monthly',
                'lastmod' => $service->updated_at?->toAtomString(),
            ];
        }

        return response()
            ->view('public.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
