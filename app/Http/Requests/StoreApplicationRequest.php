<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Support\TemporaryUploadStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreApplicationRequest extends FormRequest
{
    /**
     * Jetons FilePond effectivement transformés en fichiers.
     *
     * @var list<string>
     */
    private array $consumedTokens = [];

    /**
     * Champs dont le jeton FilePond n'a pas pu être résolu.
     *
     * @var list<string>
     */
    private array $expiredFields = [];

    /**
     * Le dépôt est public : aucun compte candidat n'existe.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Résout les jetons FilePond en fichiers avant toute validation.
     *
     * Le formulaire fonctionne de deux manières : sans JavaScript, le navigateur
     * envoie les fichiers directement ; avec FilePond, il envoie des jetons qui
     * désignent des fichiers déjà téléversés par tranches. En les convertissant
     * ici, les deux parcours empruntent ensuite exactement les mêmes règles de
     * validation — dont mimes, qui inspecte le contenu réel.
     */
    protected function prepareForValidation(): void
    {
        $storage = app(TemporaryUploadStorage::class);

        foreach (['cv', 'tcf_tef', 'passeport'] as $field) {
            $token = $this->input($field);

            if (! is_string($token) || $token === '') {
                continue;
            }

            $this->request->remove($field);

            if ($file = $storage->toUploadedFile($token)) {
                $this->files->set($field, $file);
                $this->consumedTokens[] = $token;
            } else {
                $this->expiredFields[] = $field;
            }
        }

        foreach (['diplomes', 'autres'] as $field) {
            $tokens = $this->input($field);

            if (! is_array($tokens)) {
                continue;
            }

            $this->request->remove($field);
            $files = [];

            foreach ($tokens as $token) {
                if (! is_string($token) || $token === '') {
                    continue;
                }

                if ($file = $storage->toUploadedFile($token)) {
                    $files[] = $file;
                    $this->consumedTokens[] = $token;
                } else {
                    $this->expiredFields[] = $field;
                }
            }

            if ($files !== []) {
                $this->files->set($field, $files);
            }
        }
    }

    /**
     * Signale explicitement un téléversement expiré plutôt que de laisser
     * croire au candidat qu'il a oublié de joindre un fichier.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_unique($this->expiredFields) as $field) {
                $validator->errors()->add(
                    $field,
                    'Ce téléversement a expiré. Merci de sélectionner à nouveau le fichier.',
                );
            }
        });
    }

    /**
     * Get the FilePond tokens that were turned into files.
     *
     * @return list<string>
     */
    public function consumedTokens(): array
    {
        return array_values(array_unique($this->consumedTokens));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // mimes inspecte le contenu réel du fichier, pas son extension : un
        // exécutable renommé en .pdf est donc rejeté.
        $file = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ().-]{6,}$/'],
            'country_of_residence' => ['required', 'string', 'max:255'],
            'target_program' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],

            'cv' => ['required', ...$file],
            'tcf_tef' => ['required', ...$file],
            'passeport' => ['nullable', ...$file],

            'diplomes' => ['nullable', 'array', 'max:10'],
            'diplomes.*' => $file,

            'autres' => ['nullable', 'array', 'max:10'],
            'autres.*' => $file,

            // Le consentement est vérifié ici, pas seulement par l'attribut
            // « required » du navigateur : celui-ci se contourne en trois clics.
            'consentement' => ['accepted'],

            // Honeypot : invisible pour un humain, rempli par les robots.
            'website' => ['prohibited'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Le téléphone doit être un numéro valide, de préférence au format international (ex. +237 6 XX XX XX XX).',
            'consentement.accepted' => 'Vous devez accepter la politique de confidentialité pour déposer votre dossier.',
            'website.prohibited' => 'Votre envoi a été refusé.',
        ];
    }

    /**
     * Get the candidate attributes to persist.
     *
     * @return array<string, string|null>
     */
    public function candidateAttributes(): array
    {
        /** @var array<string, string|null> $attributes */
        $attributes = $this->safe()->only([
            'first_name',
            'last_name',
            'email',
            'phone',
            'country_of_residence',
            'target_program',
            'message',
        ]);

        // On note à quoi le candidat a consenti, pas seulement qu'il a consenti :
        // sans la version du texte, le consentement n'est pas opposable.
        return [
            ...$attributes,
            'consented_at' => now()->toDateTimeString(),
            'privacy_version' => (string) config('retention.privacy_version'),
        ];
    }

    /**
     * Get the uploaded documents, keyed by document type.
     *
     * @return array<string, list<UploadedFile>>
     */
    public function documents(): array
    {
        $mapping = [
            'cv' => DocumentType::Cv,
            'tcf_tef' => DocumentType::TcfTef,
            'passeport' => DocumentType::Passeport,
            'diplomes' => DocumentType::Diplome,
            'autres' => DocumentType::Autre,
        ];

        $documents = [];

        foreach ($mapping as $field => $type) {
            $files = array_values(array_filter(
                is_array($this->file($field)) ? $this->file($field) : [$this->file($field)],
                fn (mixed $file): bool => $file instanceof UploadedFile,
            ));

            if ($files !== []) {
                $documents[$type->value] = $files;
            }
        }

        /** @var array<string, list<UploadedFile>> $documents */
        return $documents;
    }
}
