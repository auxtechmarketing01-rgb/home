import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        /**
         * Deliberately off Vite's default 5173, which belongs to the SPA in
         * `spa/`.
         *
         * Both configs used to want 5173, and the collision is silent and
         * genuinely hard to read: this server binds `[::1]` while the SPA's
         * binds `[::]`, and the more specific address wins, so `localhost:5173`
         * serves the laravel-vite-plugin placeholder page while
         * `127.0.0.1:5173` serves the real app. It looks exactly like the SPA
         * is broken.
         *
         * `strictPort` so a clash fails loudly instead of drifting to another
         * port and reintroducing the same guessing game.
         */
        port: 5199,
        strictPort: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
