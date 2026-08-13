/*
 | Formulaire de dépôt de dossier.
 |
 | Contrainte dominante : téléphone Android, 3G, scans photographiés de 5 à 10 Mo.
 | Trois mécanismes répondent à cette contrainte :
 |   1. les images sont compressées dans le navigateur avant le moindre octet envoyé ;
 |   2. l'envoi se fait par tranches, avec reprise automatique après coupure ;
 |   3. les champs texte sont conservés en localStorage et restaurés au rechargement.
 |
 | Sans JavaScript, le formulaire reste un envoi multipart classique qui fonctionne.
 */

import * as FilePond from 'filepond';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import fr from 'filepond/locale/fr-fr';
import imageCompression from 'browser-image-compression';

import 'filepond/dist/filepond.css';

FilePond.registerPlugin(FilePondPluginFileValidateType, FilePondPluginFileValidateSize);

const form = document.querySelector('[data-depot-form]');

if (form) {
    initialiserChampsTexte(form);
    initialiserTeleversements(form);
}

/* -------------------------------------------------------------------------
 | 1. Conservation des champs texte
 | ---------------------------------------------------------------------- */

function initialiserChampsTexte(form) {
    const CLE = 'ln-depot-brouillon';
    const champs = ['first_name', 'last_name', 'email', 'phone', 'country_of_residence', 'target_program', 'message'];

    // Restauration : on ne remplace jamais une valeur renvoyée par le serveur
    // après une erreur de validation, elle est plus fraîche que le brouillon.
    try {
        const brouillon = JSON.parse(localStorage.getItem(CLE) || '{}');

        champs.forEach((nom) => {
            const champ = form.elements[nom];
            if (champ && !champ.value && brouillon[nom]) {
                champ.value = brouillon[nom];
            }
        });
    } catch {
        localStorage.removeItem(CLE);
    }

    const enregistrer = () => {
        const brouillon = {};
        champs.forEach((nom) => {
            const champ = form.elements[nom];
            if (champ && champ.value) {
                brouillon[nom] = champ.value;
            }
        });

        try {
            localStorage.setItem(CLE, JSON.stringify(brouillon));
        } catch {
            // Quota dépassé ou navigation privée : le formulaire reste utilisable.
        }
    };

    champs.forEach((nom) => {
        const champ = form.elements[nom];
        if (champ) {
            champ.addEventListener('input', debounce(enregistrer, 400));
        }
    });

    // Le brouillon n'a plus de raison d'être une fois le dossier parti.
    form.addEventListener('submit', () => {
        if (form.checkValidity()) {
            localStorage.removeItem(CLE);
        }
    });
}

function debounce(fn, delai) {
    let minuteur;

    return (...args) => {
        clearTimeout(minuteur);
        minuteur = setTimeout(() => fn(...args), delai);
    };
}

/* -------------------------------------------------------------------------
 | 2. Téléversement par tranches, avec compression des images
 | ---------------------------------------------------------------------- */

function initialiserTeleversements(form) {
    const jeton = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    FilePond.setOptions({
        ...fr,
        credits: false,
        server: {
            url: form.dataset.televersementUrl,
            process: '',
            patch: '?patch=',
            revert: '',
            headers: { 'X-CSRF-TOKEN': jeton },
        },
        // Des tranches courtes : sur un réseau instable, une coupure ne fait
        // perdre au plus que la tranche en cours.
        chunkUploads: true,
        chunkForce: true,
        chunkSize: 512 * 1024,
        chunkRetryDelays: [1000, 3000, 6000, 10000, 20000],
        maxFileSize: '10MB',
        acceptedFileTypes: ['application/pdf', 'image/jpeg', 'image/png'],
        labelMaxFileSizeExceeded: 'Le fichier est trop volumineux',
        labelMaxFileSize: 'Taille maximale : {filesize}',
        labelFileTypeNotAllowed: 'Format de fichier refusé',
        fileValidateTypeLabelExpectedTypes: 'Formats acceptés : PDF, JPG ou PNG',
    });

    const ponds = [];

    form.querySelectorAll('input[type="file"][data-filepond]').forEach((input) => {
        const pond = FilePond.create(input, {
            name: input.name,
            allowMultiple: input.multiple,
            maxFiles: input.multiple ? 10 : 1,
            // La compression se déclenche à l'ajout, l'envoi part ensuite.
            instantUpload: false,
        });

        pond.on('addfile', async (erreur, fichier) => {
            if (erreur) {
                return;
            }

            // Le drapeau évite de retraiter le fichier que l'on vient d'ajouter.
            if (fichier.getMetadata('pret')) {
                pond.processFile(fichier.id);

                return;
            }

            const source = fichier.file;

            if (!source.type.startsWith('image/')) {
                fichier.setMetadata('pret', true);
                pond.processFile(fichier.id);

                return;
            }

            try {
                const compresse = await imageCompression(source, {
                    maxSizeMB: 1.5,
                    maxWidthOrHeight: 2200,
                    useWebWorker: true,
                    initialQuality: 0.8,
                });

                pond.removeFile(fichier.id, { revert: false });
                pond.addFile(new File([compresse], source.name, { type: compresse.type }), {
                    metadata: { pret: true },
                });
            } catch {
                // La compression a échoué : on envoie l'original plutôt que rien.
                fichier.setMetadata('pret', true);
                pond.processFile(fichier.id);
            }
        });

        ponds.push(pond);
    });

    empecherEnvoiPendantTeleversement(form, ponds);
}

/* -------------------------------------------------------------------------
 | 3. Garde-fou à la soumission
 | ---------------------------------------------------------------------- */

function empecherEnvoiPendantTeleversement(form, ponds) {
    const bouton = form.querySelector('[data-bouton-envoi]');
    const alerte = form.querySelector('[data-alerte-televersement]');
    const TERMINE = FilePond.FileStatus.PROCESSING_COMPLETE;

    form.addEventListener('submit', (evenement) => {
        // Un fichier encore en cours d'envoi n'a pas de jeton : partir maintenant
        // reviendrait à soumettre un dossier incomplet.
        const enCours = ponds.some(
            (pond) => pond.getFiles().some((fichier) => fichier.status !== TERMINE),
        );

        if (enCours) {
            evenement.preventDefault();
            alerte?.removeAttribute('hidden');
            alerte?.scrollIntoView({ behavior: 'smooth', block: 'center' });

            return;
        }

        alerte?.setAttribute('hidden', '');

        if (bouton) {
            bouton.disabled = true;
            bouton.textContent = bouton.dataset.libelleEnvoi ?? bouton.textContent;
        }
    });
}
