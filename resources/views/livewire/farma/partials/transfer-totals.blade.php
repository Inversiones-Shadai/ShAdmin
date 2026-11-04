<div class="ml-auto w-full rounded-xl border border-slate-200 bg-slate-50 p-4 sm:max-w-sm dark:border-slate-700 dark:bg-slate-800/50" data-test="transfer-totals">
    <dl class="space-y-3 text-sm">
        <div class="flex justify-between gap-4">
            <dt class="text-slate-600 dark:text-slate-300">Subtotal general</dt>
            <dd class="whitespace-nowrap font-semibold">L {{ number_format($totals['subtotal'], 2) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-slate-600 dark:text-slate-300">Subtotal ISV</dt>
            <dd class="whitespace-nowrap font-semibold">L {{ number_format($totals['isv'], 2) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 border-t border-slate-200 pt-3 dark:border-slate-700">
            <dt class="font-semibold">Total transferencia</dt>
            <dd class="whitespace-nowrap text-xl font-bold text-cyan-700 dark:text-cyan-300">L {{ number_format($totals['total'], 2) }}</dd>
        </div>
    </dl>
</div>
