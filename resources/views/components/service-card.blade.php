@props(['service'])

{{--
    Carte de service, menant à sa page détaillée.

    Toute la carte est cliquable, mais le lien reste porté par le titre : c'est
    lui qu'annonce un lecteur d'écran, et lui seul qui reçoit le focus clavier.
--}}
<article class="group relative flex h-full flex-col rounded-xl border border-line bg-surface-raised p-6 transition hover:border-brand hover:shadow-sm focus-within:border-brand">
    @if ($service->highlight)
        <span class="mb-3 self-start rounded-full bg-accent-soft px-3 py-1 text-xs font-medium text-accent-text">
            {{ $service->highlight }}
        </span>
    @endif

    <h3 class="font-semibold text-ink-strong">
        <a href="{{ route('services.show', $service) }}"
           class="rounded before:absolute before:inset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand group-hover:text-brand-text">
            {{ $service->title }}
        </a>
    </h3>

    <p class="mt-2 flex-1 text-sm leading-relaxed text-ink-muted">{{ $service->summary }}</p>

    <span aria-hidden="true" class="mt-4 text-sm font-medium text-brand-text">
        {{ content('services.carte_lien', 'En savoir plus') }} &rarr;
    </span>
</article>
