/*
 | Filtre des questions fréquentes, à la frappe.
 |
 | Le champ de recherche est masqué par défaut et n'est révélé qu'ici : sans
 | JavaScript, il n'apparaît pas du tout plutôt que de rester inopérant, et la
 | liste complète des questions demeure lisible.
 */

const conteneur = document.querySelector('[data-faq]');

if (conteneur) {
    const champ = conteneur.querySelector('[data-faq-recherche]');
    const messageVide = conteneur.querySelector('[data-faq-vide]');
    const questions = Array.from(conteneur.querySelectorAll('[data-faq-item]'));
    const categories = Array.from(conteneur.querySelectorAll('[data-faq-categorie]'));

    if (champ && questions.length > 0) {
        champ.hidden = false;

        champ.addEventListener('input', () => {
            const recherche = champ.value.trim().toLowerCase();
            let trouvees = 0;

            questions.forEach((question) => {
                const correspond = recherche === '' || question.dataset.faqTexte.includes(recherche);

                question.hidden = !correspond;

                // On ouvre les résultats pour que la réponse soit lisible tout
                // de suite, et on referme quand la recherche est effacée.
                question.open = correspond && recherche !== '';

                if (correspond) {
                    trouvees += 1;
                }
            });

            // Une catégorie dont plus aucune question n'est visible disparaît.
            categories.forEach((categorie) => {
                const visibles = categorie.querySelectorAll('[data-faq-item]:not([hidden])');
                categorie.hidden = visibles.length === 0;
            });

            if (messageVide) {
                messageVide.hidden = trouvees > 0;
            }
        });
    }

    // Ouvre et met en évidence la question ciblée par l'ancre partagée.
    const ancre = window.location.hash;

    if (ancre) {
        const cible = conteneur.querySelector(ancre);

        if (cible) {
            cible.open = true;
            cible.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}
