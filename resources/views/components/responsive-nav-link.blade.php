@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-[#2EB8CE] text-start text-base font-semibold text-[#126d7a] bg-[#eaf9fb] focus:outline-none focus:text-[#0e5964] focus:bg-[#d9f4f7] focus:border-[#249daf] transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-[#126d7a] hover:bg-[#eaf9fb] hover:border-[#8dd8e3] focus:outline-none focus:text-[#126d7a] focus:bg-[#eaf9fb] focus:border-[#2EB8CE] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
