import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/public.css',
                'resources/js/app.js',
                'resources/js/depot.js',
                'resources/js/faq.js',
            ],
            refresh: true,
            // Liste blanche des polices, à tenir alignée avec « polices »
            // dans config/brand.php. Elles sont téléchargées au build et
            // servies depuis le domaine du site : aucune requête vers un tiers.
            //
            // Les quatre sont construites, mais une page n'en charge qu'une —
            // celle réglée dans Apparence. Voir @fonts([...]) dans les layouts.
            fonts: [
                bunny('Instrument Sans', { weights: [400, 500, 600] }),
                bunny('Inter', { weights: [400, 500, 600] }),
                bunny('Public Sans', { weights: [400, 500, 600] }),
                bunny('Lora', { weights: [400, 500, 600] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
