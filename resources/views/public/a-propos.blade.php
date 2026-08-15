<x-public-layout
    :title="content('a_propos.meta_titre', content('global.nav_a_propos'))"
    :description="content('a_propos.meta_description')"
>
    <x-page-header
        :titre="content('a_propos.titre', 'À propos du cabinet')"
        :introduction="content('a_propos.introduction')"
    />

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="grid gap-10 md:grid-cols-2">
            <article>
                <h2 class="text-2xl font-bold tracking-tight text-ink-strong">
                    {{ content('a_propos.histoire_titre', 'Notre histoire') }}
                </h2>
                <p class="mt-4 leading-relaxed text-ink-muted">
                    {{ content('a_propos.histoire_texte') }}
                </p>
            </article>

            <article>
                <h2 class="text-2xl font-bold tracking-tight text-ink-strong">
                    {{ content('a_propos.mission_titre', 'Notre mission') }}
                </h2>
                <p class="mt-4 leading-relaxed text-ink-muted">
                    {{ content('a_propos.mission_texte') }}
                </p>
            </article>
        </div>
    </section>

    {{-- Statut professionnel.

         À quel titre le cabinet intervient, et son numéro d'enregistrement s'il
         est réglementé. Le bloc reste masqué tant que ce n'est pas renseigné :
         aucun agrément n'est supposé ni inventé ici. C'est l'information que
         cherche un candidat pour distinguer un vrai cabinet d'une arnaque. --}}
    @php
        $statutTexte = content_filled('a_propos.statut_texte');
        $statutNumero = content_filled('a_propos.statut_numero');
        $statutVerification = content_filled('a_propos.statut_verification');
    @endphp

    @if ($statutTexte || $statutNumero)
        <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
            <div class="rounded-xl border border-brand-line bg-brand-soft p-6 sm:p-8">
                <h2 class="text-lg font-semibold text-ink-strong">
                    {{ content('a_propos.statut_titre', 'À quel titre nous intervenons') }}
                </h2>

                @if ($statutTexte)
                    <p class="mt-3 leading-relaxed text-ink">{{ $statutTexte }}</p>
                @endif

                @if ($statutNumero)
                    <p class="mt-3 text-sm text-ink">
                        <span class="font-medium text-ink-strong">
                            {{ content('a_propos.statut_numero_libelle', "Numéro d'enregistrement") }} :
                        </span>
                        {{ $statutNumero }}
                    </p>
                @endif

                @if ($statutVerification)
                    <p class="mt-3 text-sm text-ink-muted">{{ $statutVerification }}</p>
                @endif
            </div>
        </section>
    @endif

    {{-- Le nombre de valeurs n'est pas figé : il découle des clés présentes
         dans le bloc de textes. Celles qui ne sont pas rédigées ne laissent
         derrière elles ni carte vide, ni bandeau gris sans contenu. --}}
    @php
        $valeurs = [];

        foreach (app(App\Support\SiteContentRepository::class)->block('a_propos') as $cle => $_) {
            if (! preg_match('/^valeur_(\d+)_titre$/', $cle, $trouve)) {
                continue;
            }

            $rang = (int) $trouve[1];

            if ($titre = content_filled("a_propos.valeur_{$rang}_titre")) {
                $valeurs[$rang] = ['titre' => $titre, 'texte' => content_filled("a_propos.valeur_{$rang}_texte")];
            }
        }

        ksort($valeurs);
    @endphp

    @if ($valeurs !== [])
        <section class="border-y border-line bg-surface-muted">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div class="grid gap-6 md:grid-cols-3">
                    @foreach ($valeurs as $valeur)
                        <article class="rounded-xl border border-line bg-surface-raised p-6">
                            <h3 class="text-lg font-semibold text-ink-strong">{{ $valeur['titre'] }}</h3>

                            @if ($valeur['texte'])
                                <p class="mt-2 text-sm leading-relaxed text-ink-muted">{{ $valeur['texte'] }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Blocs libres composés depuis le back-office. --}}
    <x-page-sections page="a-propos" />

    {{-- L'équipe n'apparaît que si au moins un membre est publié. --}}
    @php $equipe = App\Models\TeamMember::publiés(); @endphp

    @if ($equipe->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-ink-strong">
                {{ content('a_propos.equipe_titre', "L'équipe") }}
            </h2>
            <p class="mt-4 max-w-3xl leading-relaxed text-ink-muted">
                {{ content('a_propos.equipe_texte') }}
            </p>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($equipe as $membre)
                    <article class="rounded-xl border border-line bg-surface-raised p-6 text-center">
                        @if ($membre->photo_path)
                            <x-content-image
                                :chemin="$membre->photo_path"
                                :alt="$membre->photo_alt ?? $membre->name"
                                :largeur="400"
                                :hauteur="400"
                                class="mx-auto size-24 rounded-full object-cover"
                            />
                        @else
                            <span class="mx-auto flex size-24 items-center justify-center rounded-full bg-brand-soft text-2xl font-semibold text-brand-text">
                                {{ $membre->initials() }}
                            </span>
                        @endif

                        <h3 class="mt-4 font-semibold text-ink-strong">{{ $membre->name }}</h3>
                        <p class="text-sm text-brand-text">{{ $membre->role }}</p>

                        @if ($membre->bio)
                            <p class="mt-3 text-sm leading-relaxed text-ink-muted">{{ $membre->bio }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <x-cta-band
        :titre="content('a_propos.cta_titre')"
        :bouton="content('a_propos.cta_bouton', 'Déposer mon dossier')"
    />
</x-public-layout>
