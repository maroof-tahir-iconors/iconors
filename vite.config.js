import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Tailwind removed: the brief specifies Bootstrap 5 + SCSS as the
// foundation. Running both would ship two competing utility layers.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 still emits legacy @import deprecations
                // under Dart Sass 1.80+; silencing keeps the build output
                // readable without masking our own warnings.
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls'],
                quietDeps: true,
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
