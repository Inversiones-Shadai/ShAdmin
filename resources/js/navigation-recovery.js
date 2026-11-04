let pendingNavigation = null;
let navigationVersion = 0;

const pageKey = (url) => `${url.origin}${url.pathname}${url.search}`;

function recoverNavigation(url, error, signal) {
    if (! pendingNavigation || error?.name !== 'TypeError' || signal?.aborted
        || pageKey(url) !== pageKey(pendingNavigation.destination)) {
        return false;
    }

    const { destination, history } = pendingNavigation;
    pendingNavigation = null;

    if (history) {
        window.location.replace(destination.href);
    } else {
        window.location.assign(destination.href);
    }

    return true;
}

// Current Livewire catches navigation failures internally, so unhandledrejection
// cannot observe them. Watch only its same-origin GET page downloads and reads.
const originalFetch = window.fetch;
window.fetch = async function (input, options) {
    const method = options?.method ?? input?.method ?? 'GET';
    const headers = new Headers(options?.headers ?? input?.headers);
    const url = new URL(input?.url ?? input, window.location.href);
    const navigation = method.toUpperCase() === 'GET' && url.origin === window.location.origin
        && headers.get('X-Livewire-Navigate') === '1';
    const signal = options?.signal ?? input?.signal;
    const failed = (error) => queueMicrotask(() => recoverNavigation(url, error, signal));

    let response;
    try {
        response = await originalFetch.call(this, input, options);
    } catch (error) {
        if (navigation) failed(error);
        throw error;
    }

    if (navigation) {
        const readText = response.text.bind(response);
        response.text = async () => {
            try {
                return await readText();
            } catch (error) {
                failed(error);
                throw error;
            }
        };
    }

    return response;
};

document.addEventListener('livewire:navigate', (event) => {
    const version = ++navigationVersion;

    // Wait until every listener has had a chance to cancel the navigation.
    queueMicrotask(() => {
        if (version !== navigationVersion || event.defaultPrevented || event.detail.cached) {
            return;
        }

        const destination = new URL(event.detail.url, window.location.href);

        if (destination.origin === window.location.origin) {
            pendingNavigation = { destination, history: event.detail.history };
        }
    });
});

document.addEventListener('livewire:navigating', () => {
    navigationVersion++;
    pendingNavigation = null;
});

document.addEventListener('livewire:navigated', () => {
    navigationVersion++;
    pendingNavigation = null;
});

window.addEventListener('unhandledrejection', (event) => {
    // Older Livewire versions rethrow failed page downloads. Keep that fallback
    // restricted to navigation, leaving component/API errors visible.
    const error = event.reason;

    if (! pendingNavigation || error?.name !== 'TypeError'
        || typeof error.stack !== 'string' || ! error.stack.includes('sendNavigateRequest')) {
        return;
    }

    if (recoverNavigation(pendingNavigation.destination, error)) event.preventDefault();
});
