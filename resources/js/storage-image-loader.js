import { loadSessionImage } from './session-image-cache';

const load = (image) => {
    const source = image.dataset.sessionImageSrc || image.getAttribute('src') || '';
    if (!source || /^(data:|blob:)/.test(source) || image.dataset.sessionImageBound === source) return;
    image.dataset.sessionImageBound = source;
    loadSessionImage(source).then((url) => {
        if (image.isConnected && image.dataset.sessionImageBound === source) {
            image.src = url;
            image.classList.remove('hidden');
        }
    }).catch(() => {
        if (image.isConnected && image.dataset.sessionImageBound === source) image.src = source;
    });
};

const scan = () => {
    document.querySelectorAll('img:not([data-image-viewer-image])').forEach(load);
    document.querySelectorAll('[data-image-viewer-src]:not([data-image-viewer-on-demand])').forEach((trigger) => {
        const source = trigger.dataset.imageViewerSrc;
        if (source) loadSessionImage(source).catch(() => {});
    });
};
let scheduled = false;
const schedule = () => {
    if (scheduled) return;
    scheduled = true;
    queueMicrotask(() => { scheduled = false; scan(); });
};
const init = () => {
    scan();
    // wire:navigate replaces the body; keep watching the stable document root
    // so images inserted later by a Livewire modal are still discovered.
    new MutationObserver(schedule).observe(document.documentElement, {
        childList: true, subtree: true, attributes: true,
        attributeFilter: ['data-session-image-src', 'data-image-viewer-src'],
    });
};
document.addEventListener('livewire:navigated', schedule);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
else init();
