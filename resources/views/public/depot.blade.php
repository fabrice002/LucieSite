<x-public-layout
    :title="content('depot.meta_titre', content('global.nav_deposer'))"
    :description="content('depot.meta_description')"
>
    @php
        $labelClass = 'block text-sm font-medium text-slate-800';
        $inputClass = 'mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-base shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-200 focus:outline-none';
        $fileClass = 'mt-1 block w-full cursor-pointer rounded-md border border-dashed border-slate-300 px-3 py-3 text-sm file:mr-3 file:rounded file:border-0 file:bg-brand-100 file:px-3 file:py-1.5 file:text-brand-800';
        $helpClass = 'mt-1 text-xs text-slate-500';
        $errorClass = 'mt-1 text-sm text-red-600';
    @endphp

    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <h1 class="text-3xl font-bold tracking-tight text-brand-900">
            {{ content('depot.titre', 'Déposer mon dossier') }}
        </h1>

        <p class="mt-4 leading-relaxed text-slate-600">
            {{ content('depot.introduction') }}
        </p>

        @if ($errors->any())
            <div role="alert" class="mt-8 rounded-md border border-red-200 bg-red-50 p-4">
                <p class="font-medium text-red-800">
                    Votre dossier n'a pas pu être envoyé. Corrigez les points suivants :
                </p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ route('depot.store') }}"
              enctype="multipart/form-data"
              data-depot-form
              data-televersement-url="{{ url('televersement') }}"
              class="mt-10 space-y-10">
            @csrf

            {{-- Honeypot : masqué pour un humain, rempli par les robots. --}}
            <div class="hidden" aria-hidden="true">
                <label for="website">Ne remplissez pas ce champ</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            {{-- Identité --}}
            <fieldset class="space-y-5">
                <legend class="text-lg font-semibold text-brand-900">
                    {{ content('depot.section_identite', 'Vos informations') }}
                </legend>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="{{ $labelClass }}">
                            {{ content('depot.label_prenom', 'Prénom') }} <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="first_name" name="first_name" required autocomplete="given-name"
                               value="{{ old('first_name') }}" class="{{ $inputClass }}">
                        @error('first_name')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="last_name" class="{{ $labelClass }}">
                            {{ content('depot.label_nom', 'Nom') }} <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="last_name" name="last_name" required autocomplete="family-name"
                               value="{{ old('last_name') }}" class="{{ $inputClass }}">
                        @error('last_name')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="email" class="{{ $labelClass }}">
                            {{ content('depot.label_email', 'Adresse e-mail') }} <span class="text-red-600">*</span>
                        </label>
                        <input type="email" id="email" name="email" required autocomplete="email" inputmode="email"
                               value="{{ old('email') }}" class="{{ $inputClass }}">
                        @error('email')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="phone" class="{{ $labelClass }}">
                            {{ content('depot.label_telephone', 'Téléphone') }} <span class="text-red-600">*</span>
                        </label>
                        <input type="tel" id="phone" name="phone" required autocomplete="tel" inputmode="tel"
                               value="{{ old('phone') }}" class="{{ $inputClass }}">
                        <p class="{{ $helpClass }}">{{ content('depot.aide_telephone') }}</p>
                        @error('phone')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="country_of_residence" class="{{ $labelClass }}">
                            {{ content('depot.label_pays', 'Pays de résidence') }} <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="country_of_residence" name="country_of_residence" required
                               autocomplete="country-name" value="{{ old('country_of_residence') }}" class="{{ $inputClass }}">
                        @error('country_of_residence')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="target_program" class="{{ $labelClass }}">
                            {{ content('depot.label_programme', 'Programme visé') }}
                        </label>
                        <input type="text" id="target_program" name="target_program"
                               value="{{ old('target_program') }}" class="{{ $inputClass }}">
                        <p class="{{ $helpClass }}">{{ content('depot.aide_programme') }}</p>
                        @error('target_program')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="message" class="{{ $labelClass }}">
                        {{ content('depot.label_message', 'Votre message') }}
                    </label>
                    <textarea id="message" name="message" rows="5" class="{{ $inputClass }}">{{ old('message') }}</textarea>
                    <p class="{{ $helpClass }}">{{ content('depot.aide_message') }}</p>
                    @error('message')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                </div>
            </fieldset>

            {{-- Documents --}}
            <fieldset class="space-y-5">
                <legend class="text-lg font-semibold text-brand-900">
                    {{ content('depot.section_documents', 'Vos documents') }}
                </legend>

                <p class="rounded-md bg-slate-50 p-3 text-sm text-slate-600">
                    {{ content('depot.aide_documents') }}
                </p>

                <div>
                    <label for="cv" class="{{ $labelClass }}">
                        {{ content('depot.label_cv', 'CV au format canadien') }} <span class="text-red-600">*</span>
                    </label>
                    <input type="file" id="cv" name="cv" required accept="application/pdf,image/jpeg,image/png" data-filepond class="{{ $fileClass }}">
                    <p class="{{ $helpClass }}">{{ content('depot.aide_cv') }}</p>
                    @error('cv')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="tcf_tef" class="{{ $labelClass }}">
                        {{ content('depot.label_tcf_tef', 'Résultat TCF ou TEF') }} <span class="text-red-600">*</span>
                    </label>
                    <input type="file" id="tcf_tef" name="tcf_tef" required accept="application/pdf,image/jpeg,image/png" data-filepond class="{{ $fileClass }}">
                    <p class="{{ $helpClass }}">{{ content('depot.aide_tcf_tef') }}</p>
                    @error('tcf_tef')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="passeport" class="{{ $labelClass }}">
                        {{ content('depot.label_passeport', 'Passeport') }}
                    </label>
                    <input type="file" id="passeport" name="passeport" accept="application/pdf,image/jpeg,image/png" data-filepond class="{{ $fileClass }}">
                    <p class="{{ $helpClass }}">{{ content('depot.aide_passeport') }}</p>
                    @error('passeport')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="diplomes" class="{{ $labelClass }}">
                        {{ content('depot.label_diplomes', 'Diplômes') }}
                    </label>
                    <input type="file" id="diplomes" name="diplomes[]" multiple accept="application/pdf,image/jpeg,image/png" data-filepond class="{{ $fileClass }}">
                    <p class="{{ $helpClass }}">{{ content('depot.aide_diplomes') }}</p>
                    @error('diplomes.*')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="autres" class="{{ $labelClass }}">
                        {{ content('depot.label_autres', 'Autres documents') }}
                    </label>
                    <input type="file" id="autres" name="autres[]" multiple accept="application/pdf,image/jpeg,image/png" data-filepond class="{{ $fileClass }}">
                    <p class="{{ $helpClass }}">{{ content('depot.aide_autres') }}</p>
                    @error('autres.*')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                </div>
            </fieldset>

            <div class="border-t border-slate-200 pt-6">
                <p data-alerte-televersement hidden role="alert"
                   class="mb-5 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Vos fichiers sont encore en cours d'envoi. Patientez jusqu'à la fin de l'envoi
                    avant de valider votre dossier.
                </p>

                <p class="text-xs leading-relaxed text-slate-500">{{ content('depot.mention_donnees') }}</p>

                <button type="submit"
                        data-bouton-envoi
                        data-libelle-envoi="Envoi en cours…"
                        class="mt-5 w-full rounded-md bg-brand-700 px-6 py-3 font-medium text-white transition hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                    {{ content('depot.bouton_envoyer', 'Envoyer mon dossier') }}
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
        @vite('resources/js/depot.js')
    @endpush
</x-public-layout>
