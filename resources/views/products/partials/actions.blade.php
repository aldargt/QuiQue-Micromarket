<div class="flex items-center justify-end gap-3">
    <a href="{{ route('products.edit', $product) }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Editar</a>
    <form method="POST" action="{{ route('products.toggle', $product) }}">@csrf @method('PATCH')
        <button type="submit" class="text-sm font-semibold {{ $product->is_active ? 'text-amber-700 hover:text-amber-900' : 'text-green-700 hover:text-green-900' }}">{{ $product->is_active ? 'Desactivar' : 'Activar' }}</button>
    </form>
</div>
