@props(['value', 'required' => false])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-gray-700']) }}>
    {{ $value ?? $slot }}
    @if ($required)
        <span class="ms-0.5 text-base font-bold leading-none text-red-600 dark:text-red-400" aria-hidden="true">*</span>
        <span class="sr-only">(obligatorio)</span>
    @endif
</label>
