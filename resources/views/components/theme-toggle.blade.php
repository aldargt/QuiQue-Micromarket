@props(['showLabel' => false])

<button type="button" data-theme-toggle onclick="window.toggleTheme()" aria-label="Activar modo oscuro" title="Activar modo oscuro" {{ $attributes->class(['inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[#a8dce4] bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-[#2EB8CE] hover:bg-[#eefbfc] dark:border-slate-600 dark:bg-[#2b323c] dark:text-slate-100 dark:hover:border-[#2EB8CE] dark:hover:bg-[#313b46]']) }}>
    <svg class="h-5 w-5 dark:hidden" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
    <svg class="hidden h-5 w-5 dark:block" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
    @if ($showLabel)
        <span data-theme-label>Modo oscuro</span>
    @endif
</button>
