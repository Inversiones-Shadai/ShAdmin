import { loadSessionImage } from './session-image-cache';

const cache = window.ShAdminImageCache instanceof Map
    ? window.ShAdminImageCache
    : new Map();

window.ShAdminImageCache = cache;

const viewer = {
    dialog: null,
    image: null,
    title: null,
    loading: null,
    cacheStatus: null,
    bound: false,
    activeSource: null,

    init() {
        this.refresh();

        if (this.bound) {
            return;
        }

        this.bound = true;
        document.addEventListener('click', (event) => this.handleClick(event), true);
        document.addEventListener('image-viewer:show', (event) => this.handleImage(event));
        document.addEventListener('image-viewer:error', () => this.close());
        document.addEventListener('image-viewer:invalidate', (event) => this.invalidate(event));
        document.addEventListener('livewire:navigated', () => this.refresh());
    },

    refresh() {
        this.dialog = document.getElementById('global-image-viewer');
        this.image = this.dialog?.querySelector('[data-image-viewer-image]') || null;
        this.title = this.dialog?.querySelector('#global-image-viewer-title') || null;
        this.loading = this.dialog?.querySelector('[data-image-viewer-loading]') || null;
        this.cacheStatus = this.dialog?.querySelector('[data-image-viewer-cache-status]') || null;

        if (this.dialog instanceof HTMLDialogElement && this.dialog.dataset.viewerBound !== 'true') {
            this.dialog.dataset.viewerBound = 'true';
            this.dialog.addEventListener('click', (event) => {
                if (event.target === this.dialog) {
                    this.close();
                }
            });
            this.dialog.addEventListener('close', () => this.reset());
        }
    },

    handleClick(event) {
        const closeButton = event.target instanceof Element
            ? event.target.closest('[data-image-viewer-close]')
            : null;

        if (closeButton) {
            event.preventDefault();
            this.close();

            return;
        }

        const trigger = event.target instanceof Element
            ? event.target.closest('[data-image-viewer-key]')
            : null;

        if (!trigger) {
            return;
        }

        const key = trigger.dataset.imageViewerKey || '';
        const name = trigger.dataset.imageViewerName || 'Imagen';
        const cached = cache.get(key);

        this.activeSource = null;
        this.openLoading(name);

        const source = trigger.dataset.imageViewerSrc || '';
        if (source) {
            event.preventDefault();
            event.stopImmediatePropagation();
            this.activeSource = source;
            loadSessionImage(source).then((url) => {
                if (this.activeSource === source) this.show(name, url, true);
            }).catch(() => {
                if (this.activeSource === source) this.show(name, source, false);
            });
            return;
        }

        if (!cached) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        cache.delete(key);
        cache.set(key, cached);
        this.show(name, cached, true);
    },

    handleImage(event) {
        const detail = event.detail || {};
        const key = String(detail.key || '');
        const name = String(detail.name || 'Imagen');
        const src = String(detail.src || '');

        if (key === '' || !/^(data:image\/|https:\/\/|blob:)/.test(src)) {
            this.close();

            return;
        }

        this.activeSource = src;
        loadSessionImage(src).then((url) => {
            if (this.activeSource !== src) return;
            this.put(key, url);
            this.show(name, url, false);
        }).catch(() => {
            if (this.activeSource === src) this.show(name, src, false);
        });
    },

    invalidate(event) {
        const key = String(event.detail?.key || '');

        if (key !== '') {
            cache.delete(key);
        }
    },

    put(key, src) {
        cache.delete(key);
        cache.set(key, src);

    },

    openLoading(name) {
        this.refresh();

        if (!(this.dialog instanceof HTMLDialogElement)) {
            return;
        }

        if (this.title) {
            this.title.textContent = name;
        }

        this.image?.classList.add('hidden');
        this.loading?.classList.remove('hidden');

        if (this.cacheStatus) {
            this.cacheStatus.textContent = 'Consultando imagen segura...';
        }

        if (!this.dialog.open) {
            this.dialog.showModal();
        }
    },

    show(name, src, fromCache) {
        this.openLoading(name);

        if (this.image) {
            this.image.src = src;
            this.image.alt = `Imagen de ${name}`;
            this.image.classList.remove('hidden');
        }

        this.loading?.classList.add('hidden');

        if (this.cacheStatus) {
            this.cacheStatus.textContent = fromCache
                ? 'Imagen recuperada del caché de esta sesión'
                : 'Imagen guardada temporalmente en esta sesión';
        }
    },

    close() {
        if (this.dialog instanceof HTMLDialogElement && this.dialog.open) {
            this.dialog.close();
        }
    },

    reset() {
        this.activeSource = null;
        if (this.image) {
            this.image.src = '';
            this.image.alt = '';
            this.image.classList.add('hidden');
        }

        this.loading?.classList.remove('hidden');
    },
};

window.ShAdminImageViewer = viewer;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => viewer.init(), { once: true });
} else {
    viewer.init();
}
