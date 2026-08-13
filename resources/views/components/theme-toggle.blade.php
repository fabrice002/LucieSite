{{--
    Bascule clair / sombre.

    Écrit dans la même clé que le back-office (« flux.appearance ») : le choix
    du visiteur le suit du site public jusqu'à l'espace d'administration.
    Sans JavaScript, le bouton est simplement masqué et le site suit la
    préférence du système.
--}}
<button type="button"
        data-theme-toggle
        hidden
        class="flex size-9 items-center justify-center rounded-md border border-line text-ink-muted transition hover:border-brand hover:text-brand-text"
        aria-label="Changer de thème">
    <svg class="size-4 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.5A9.75 9.75 0 0 1 8.5 2.25a9.75 9.75 0 1 0 13.25 13.25Z" />
    </svg>
    <svg class="hidden size-4 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5m-15 0H3m15.36-6.36-1.06 1.06M6.7 17.3l-1.06 1.06m12.72 0-1.06-1.06M6.7 6.7 5.64 5.64M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
    </svg>
</button>

<script>
    (function () {
        var bouton = document.currentScript.previousElementSibling;

        // Le bouton n'apparaît que si le JavaScript fonctionne.
        bouton.hidden = false;

        bouton.addEventListener('click', function () {
            var sombre = document.documentElement.classList.toggle('dark');

            try {
                localStorage.setItem('flux.appearance', sombre ? 'dark' : 'light');
            } catch (e) {}
        });
    })();
</script>
