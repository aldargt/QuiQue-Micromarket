@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'min-h-10 rounded-lg border-gray-300 bg-white shadow-sm transition placeholder:text-gray-400 focus:border-[#2EB8CE] focus:ring-[#2EB8CE] disabled:bg-gray-100 disabled:text-gray-500']) }}>
