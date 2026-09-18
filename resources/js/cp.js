import Settings from './pages/Settings.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('rechnerei-inquiries::settings', Settings);
});
