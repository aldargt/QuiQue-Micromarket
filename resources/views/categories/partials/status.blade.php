<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
    {{ $category->is_active ? 'Activa' : 'Inactiva' }}
</span>
