@props(['role'])

<span {{ $attributes->class(['inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#dff6f9] text-[#167b8b] ring-1 ring-[#a8dce4] dark:bg-[#24434a] dark:text-[#8be0eb] dark:ring-[#37656e]']) }} aria-hidden="true">
    @if ($role === 'administrator')
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 21h16M6 21V8l6-4 6 4v13M9 11h.01M15 11h.01M9 15h.01M15 15h.01M11 21v-3h2v3"/></svg>
    @else
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Zm2 3h10v4H7V7Zm0 7h2m3 0h2m3 0h.01M7 17h2m3 0h2m3 0h.01"/></svg>
    @endif
</span>
