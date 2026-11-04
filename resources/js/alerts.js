import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const theme = () => {
    const dark = document.documentElement.classList.contains('dark');

    return {
        background: dark ? '#0f172a' : '#ffffff',
        color: dark ? '#f8fafc' : '#0f172a',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: dark ? '#475569' : '#64748b',
    };
};

const normalize = (options) => {
    if (typeof options === 'string') {
        return { title: options };
    }

    return options ?? {};
};

const waitForInterface = () => new Promise((resolve) => {
    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(resolve);
    });
});

const topLayerTarget = () => {
    const dialogs = document.querySelectorAll('[data-flux-modal] > dialog[open]');

    return dialogs.item(dialogs.length - 1) || document.body;
};

const topLayer = () => ({
    target: topLayerTarget(),
    topLayer: true,
});

const alerts = {
    async alert(options = {}) {
        await waitForInterface();

        return Swal.fire({
            icon: 'info',
            title: 'Aviso',
            confirmButtonText: 'Aceptar',
            heightAuto: false,
            ...theme(),
            ...normalize(options),
            ...topLayer(),
        });
    },

    async toast(options = {}) {
        const settings = normalize(options);

        await waitForInterface();

        return Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            ...theme(),
            ...settings,
            ...topLayer(),
            didOpen(toast) {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
                settings.didOpen?.(toast);
            },
        });
    },
};

window.ShAdminAlerts = alerts;

const registerLivewireListeners = () => {
    if (! window.Livewire || window.__shAdminSweetAlertListeners) {
        return;
    }

    window.__shAdminSweetAlertListeners = [
        window.Livewire.on('sweetalert:alert', (options) => alerts.alert(options)),
        window.Livewire.on('sweetalert:toast', (options) => alerts.toast(options)),
    ];
};

document.addEventListener('livewire:init', registerLivewireListeners);
registerLivewireListeners();
