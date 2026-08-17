@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-[#2EB8CE] text-sm font-semibold leading-5 text-gray-900 focus:outline-none focus:border-[#249daf] transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:border-[#8dd8e3] hover:text-[#167b8b] focus:outline-none focus:border-[#2EB8CE] focus:text-[#167b8b] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
