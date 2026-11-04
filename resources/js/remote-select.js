const register = () => {
    if (!window.Alpine || window.__remoteSelectRegistered === 'floating-natural-v1') return;
    window.__remoteSelectRegistered = 'floating-natural-v1';
    window.Alpine.data('remoteSelect', (config) => ({
        value: config.value,
        open: false,
        search: '',
        items: [],
        label: config.label || '',
        page: 1,
        lastPage: 1,
        total: 0,
        loading: false,
        selecting: false,
        error: '',
        controller: null,
        version: 0,
        floatingCleanup: null,
        init() {
            this.$watch('value', () => this.resolve());
            if (!this.label) this.resolve();
        },
        destroy() { this.close(); },
        close(returnFocus = false) {
            this.open = false;
            this.controller?.abort();
            this.version++;
            this.loading = false;
            this.floatingCleanup?.();
            this.floatingCleanup = null;
            const panel = this.$refs.panel;
            if (typeof panel?.hidePopover === 'function') {
                try { panel.hidePopover(); } catch { /* The owner dialog may already have closed. */ }
            }
            if (returnFocus) this.$refs.trigger?.focus({ preventScroll: true });
        },
        popoverToggled(event) {
            if (event.newState === 'closed' && this.open) this.close();
        },
        startFloating() {
            const panel = this.$refs.panel;
            if (!panel) return;
            this.floatingCleanup?.();
            const reposition = (event) => {
                if (event?.target && panel.contains(event.target)) return;
                this.positionPanel();
            };
            window.addEventListener('resize', reposition);
            document.addEventListener('scroll', reposition, { capture: true });
            const ownerDialog = this.$refs.trigger?.closest?.('dialog');
            const ownerClosed = () => this.close();
            ownerDialog?.addEventListener('close', ownerClosed);
            const dialogObserver = ownerDialog && typeof MutationObserver === 'function' ? new MutationObserver(() => {
                if (!ownerDialog.open) this.close();
            }) : null;
            dialogObserver?.observe(ownerDialog, { attributes: true, attributeFilter: ['open'] });
            const viewport = window.visualViewport;
            viewport?.addEventListener('resize', reposition);
            viewport?.addEventListener('scroll', reposition);
            let resizeFrame;
            const observer = typeof ResizeObserver === 'function' ? new ResizeObserver(() => {
                window.cancelAnimationFrame(resizeFrame);
                resizeFrame = window.requestAnimationFrame(() => this.positionPanel());
            }) : null;
            observer?.observe(panel);
            if (this.$refs.trigger) observer?.observe(this.$refs.trigger);
            this.floatingCleanup = () => {
                window.removeEventListener('resize', reposition);
                document.removeEventListener('scroll', reposition, { capture: true });
                ownerDialog?.removeEventListener('close', ownerClosed);
                dialogObserver?.disconnect();
                viewport?.removeEventListener('resize', reposition);
                viewport?.removeEventListener('scroll', reposition);
                observer?.disconnect();
                window.cancelAnimationFrame?.(resizeFrame);
            };
        },
        positionPanel() {
            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;
            if (!this.open || !trigger || !panel) return;
            const rect = trigger.getBoundingClientRect();
            const viewport = window.visualViewport;
            const margin = 8, gap = 6;
            const leftEdge = (viewport?.offsetLeft ?? 0) + margin;
            const topEdge = (viewport?.offsetTop ?? 0) + margin;
            const rightEdge = leftEdge + (viewport?.width ?? window.innerWidth) - margin * 2;
            const bottomEdge = topEdge + (viewport?.height ?? window.innerHeight) - margin * 2;
            if (rect.width <= 0 || rect.bottom < topEdge || rect.top > bottomEdge || trigger.isConnected === false) {
                this.close();
                return;
            }
            const width = Math.min(Math.max(rect.width, 280), rightEdge - leftEdge);
            panel.style.width = `${width}px`;
            const below = Math.max(0, bottomEdge - rect.bottom - gap);
            const above = Math.max(0, rect.top - topEdge - gap);
            const useAbove = below < 420 && above > below;
            const available = useAbove ? above : below;
            const overlap = available < Math.min(180, bottomEdge - topEdge);
            const height = Math.min(420, overlap ? bottomEdge - topEdge : available);
            // Constrain only to the viewport. Loading/results never determine the panel's height.
            // Anchor upwards with bottom so short lists stay next to their trigger as well.
            Object.assign(panel.style, {
                position: 'fixed', inset: 'auto', margin: '0',
                left: `${Math.max(leftEdge, Math.min(rect.left, rightEdge - width))}px`,
                top: overlap ? `${topEdge}px` : useAbove ? 'auto' : `${rect.bottom + gap}px`,
                bottom: !overlap && useAbove ? `${window.innerHeight - rect.top + gap}px` : 'auto',
                height: 'auto',
                maxHeight: `${height}px`,
            });
        },
        async resolve() {
            if (!this.value) { this.label = ''; return; }
            const cached = this.items.find((item) => String(item.id) === String(this.value));
            if (cached) { this.label = cached.label; return; }
            const selected = String(this.value);
            try {
                const result = await this.request({ selected });
                if (String(this.value) !== selected) return;
                const item = result.data[0];
                this.label = item?.label || `#${selected}`;
                if (item && config.cache) await this.$wire.rememberSelectorChoice(config.source, selected);
            } catch { if (String(this.value) === selected) this.label = `#${selected}`; }
        },
        async request(params, signal) {
            const url = new URL(config.url, window.location.href);
            Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
            Object.entries(config.filters || {}).forEach(([key, value]) => {
                if (value) url.searchParams.set(`filters[${key}]`, value);
            });
            const response = await fetch(url, { signal, credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(response.status === 401 ? 'La sesión terminó. Inicia sesión nuevamente.' : 'No fue posible cargar las opciones. Reintenta.');
            return response.json();
        },
        async show() {
            this.open = true;
            this.search = '';
            this.$nextTick(() => {
                if (!this.open) return;
                const panel = this.$refs.panel;
                if (typeof panel?.showPopover === 'function') {
                    try { panel.showPopover(); } catch { this.close(); return; }
                }
                this.startFloating();
                this.positionPanel();
                if (this.open) this.$refs.search?.focus({ preventScroll: true });
            });
            await this.load(1);
        },
        async load(page = 1) {
            const version = ++this.version;
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;
            this.error = '';
            this.items = [];
            try {
                const result = await this.request({ page, search: this.search.slice(0, 255) }, this.controller.signal);
                if (version !== this.version) return;
                this.items = result.data;
                this.page = result.meta.current_page;
                this.lastPage = result.meta.last_page;
                this.total = result.meta.total;
            } catch (error) {
                if (error.name !== 'AbortError' && version === this.version) this.error = error.message;
            } finally {
                if (version === this.version) {
                    this.loading = false;
                    this.$nextTick(() => this.positionPanel());
                }
            }
        },
        async select(item) {
            if (this.selecting) return;
            const version = this.version;
            this.selecting = true;
            try {
                if (config.cache) {
                    const remembered = await this.$wire.rememberSelectorChoice(config.source, String(item.id));
                    if (!remembered.ok) { this.error = remembered.message; return; }
                }
                if (version !== this.version) return;
                this.label = item.label;
                this.value = String(item.id);
                this.close(true);
            } catch {
                this.error = 'No fue posible confirmar la opción. Reintenta.';
            } finally {
                this.selecting = false;
            }
        },
        clear() { this.value = ''; this.label = ''; this.close(true); },
    }));
};
document.addEventListener('alpine:init', register);
register();
