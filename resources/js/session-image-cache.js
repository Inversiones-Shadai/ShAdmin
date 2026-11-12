const currentSession = () => document.querySelector('meta[name="shadmin-image-session"]')?.content || '';
let session = currentSession();
const memory = new Map();
const pending = new Map();
const identity = (source) => {
    const url = new URL(source, location.href);
    if (url.searchParams.has('X-Amz-Signature')) url.search = '';
    return url.href;
};
const openStorage = (sessionId) => sessionId && 'caches' in window
    ? caches.keys().then(async (keys) => {
        const namespace = `shadmin-images-${sessionId}`;
        await Promise.all(keys.filter((key) => key.startsWith('shadmin-images-') && key !== namespace).map((key) => caches.delete(key)));
        return caches.open(namespace);
    }).catch(() => null)
    : Promise.resolve(null);
let storage = openStorage(session);

const synchronizeSession = () => {
    const next = currentSession();
    if (next === session) return;
    memory.forEach((url) => URL.revokeObjectURL(url));
    memory.clear();
    pending.clear();
    window.ShAdminImageCache?.clear();
    if (window.ShAdminImageViewer) {
        window.ShAdminImageViewer.activeSource = null;
        window.ShAdminImageViewer.close();
    }
    session = next;
    storage = openStorage(session);
};

const fetchImage = async (source) => {
    const local = new URL(source, location.href).origin === new URL(location.href).origin;
    const response = await fetch(source, { credentials: local ? 'same-origin' : 'omit', cache: 'no-store' });
    if (!response.ok || !response.headers.get('Content-Type')?.startsWith('image/')) {
        throw new Error('No fue posible cargar la imagen.');
    }
    return response;
};

const load = (source, fallbackSource) => {
    synchronizeSession();
    if (source.startsWith('data:image/') || source.startsWith('blob:')) return Promise.resolve(source);
    const key = identity(source);
    if (memory.has(key)) return Promise.resolve(memory.get(key));
    if (pending.has(key)) return pending.get(key);
    const generation = session;
    const request = (async () => {
        const cache = await storage;
        let response = await cache?.match(key).catch(() => null);
        if (!response) {
            response = await fetchImage(source).catch((error) => {
                if (!fallbackSource || generation !== currentSession()) throw error;
                return fetchImage(fallbackSource);
            });
            await cache?.put(key, response.clone()).catch(() => {});
        }
        const objectUrl = URL.createObjectURL(await response.blob());
        if (generation !== session) {
            URL.revokeObjectURL(objectUrl);
            throw new Error('La sesión de imágenes cambió.');
        }
        memory.set(key, objectUrl);
        return objectUrl;
    })().finally(() => {
        if (pending.get(key) === request) pending.delete(key);
    });
    pending.set(key, request);
    return request;
};

window.ShAdminSessionImages = { load };
export { load as loadSessionImage };
