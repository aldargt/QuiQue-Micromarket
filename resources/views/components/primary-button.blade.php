<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-10 items-center justify-center rounded-lg border border-transparent bg-[#2EB8CE] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#249daf] focus:outline-none focus:ring-2 focus:ring-[#2EB8CE] focus:ring-offset-2 active:bg-[#1d8292] disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
