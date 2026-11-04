<dialog
    id="global-image-viewer"
    data-test="global-image-viewer"
    aria-labelledby="global-image-viewer-title"
    class="m-auto w-[min(94vw,48rem)] rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/75 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
>
    <div class="flex min-h-[24rem] flex-col">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-400">
                    Vista previa
                </p>
                <h2 id="global-image-viewer-title" class="mt-1 truncate text-lg font-semibold">
                    Imagen
                </h2>
            </div>
            <button
                type="button"
                data-image-viewer-close
                aria-label="Cerrar visor"
                class="grid size-9 shrink-0 place-items-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
            >
                <flux:icon.x-mark class="size-5" />
            </button>
        </header>

        <div class="relative grid min-h-80 flex-1 place-items-center overflow-hidden bg-slate-100 p-5 dark:bg-slate-950">
            <div data-image-viewer-loading class="grid place-items-center gap-3 text-slate-500 dark:text-slate-400">
                <span class="size-10 animate-spin rounded-full border-2 border-slate-300 border-t-emerald-500 dark:border-slate-700 dark:border-t-emerald-400"></span>
                <span class="text-sm">Cargando imagen...</span>
            </div>
            <img
                data-image-viewer-image
                src=""
                alt=""
                class="hidden max-h-[65vh] max-w-full rounded-xl bg-white object-contain shadow-lg"
            />
        </div>

        <footer class="flex items-center justify-between gap-3 border-t border-slate-200 px-5 py-3 dark:border-slate-700">
            <span data-image-viewer-cache-status class="text-xs text-slate-500 dark:text-slate-400"></span>
            <button
                type="button"
                data-image-viewer-close
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium transition hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800"
            >
                Cerrar
            </button>
        </footer>
    </div>
</dialog>
