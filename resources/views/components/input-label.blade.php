@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-gray-700']) }}>
    {{ $value ?? $slot }}
</label>
