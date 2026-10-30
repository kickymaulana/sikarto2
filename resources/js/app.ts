import '../css/app.css';
import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/inter/800.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent, Transition } from 'vue';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'SI KARTO';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')) as Promise<
            DefineComponent
        >,
    setup({ el, App, props, plugin }) {
        createApp({
            render() {
                return h(Transition, {
                    name: 'slide',
                    mode: 'out-in',
                }, () => h('div', { style: 'min-height:100vh' }, h(App, props)));
            },
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
});