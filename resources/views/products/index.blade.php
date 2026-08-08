<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Productos</h1>
                <p class="mt-1 text-sm text-gray-600">Consulta y administra los productos de tu sucursal.</p>
            </div>
            <a href="{{ route('products.create') }}" class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2">Crear producto</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="GET" action="{{ route('products.index') }}" class="mb-6 grid gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-2 lg:grid-cols-5">
                <div class="sm:col-span-2 lg:col-span-2">
                    <x-input-label for="search" value="Buscar" />
                    <x-text-input id="search" name="search" type="search" class="mt-1 block w-full" :value="$filters['search']" placeholder="Nombre, código de barras o interno" />
                </div>
                <div>
                    <x-input-label for="category" value="Categoría" />
                    <select id="category" name="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                        <option value="">Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) $filters['category'] === $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (inactiva)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="status" value="Estado" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                        <option value="">Todos</option>
                        <option value="active" @selected($filters['status'] === 'active')>Activos</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="stock" value="Stock" />
                    <select id="stock" name="stock" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                        <option value="">Todos</option>
                        <option value="zero" @selected($filters['stock'] === 'zero')>Stock cero</option>
                    </select>
                </div>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-5 lg:justify-end">
                    <x-primary-button>Aplicar filtros</x-primary-button>
                    <a href="{{ route('products.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Limpiar</a>
                </div>
            </form>

            <div class="hidden overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 lg:block">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Producto</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Categoría</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Precio</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Stock</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Estado</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Acciones</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($products as $product)
                            <tr>
                                <td class="px-5 py-4"><div class="font-medium text-gray-900">{{ $product->name }}</div><div class="mt-1 text-xs text-gray-500">{{ $product->displayCode() }} · {{ $product->unit->label() }}</div></td>
                                <td class="px-5 py-4 text-sm text-gray-700">{{ $product->category->name }}</td>
                                <td class="px-5 py-4 text-sm font-medium text-gray-900">Bs {{ number_format((float) $product->sale_price, 2, ',', '.') }}</td>
                                <td class="px-5 py-4 text-sm text-gray-700">{{ number_format((float) $product->stock, 3, ',', '.') }}</td>
                                <td class="px-5 py-4">@include('products.partials.status')</td>
                                <td class="px-5 py-4">@include('products.partials.actions')</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">No se encontraron productos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:hidden">
                @forelse ($products as $product)
                    <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div class="flex items-start justify-between gap-3"><div><h2 class="font-semibold text-gray-900">{{ $product->name }}</h2><p class="mt-1 text-xs text-gray-500">{{ $product->displayCode() }}</p></div>@include('products.partials.status')</div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-gray-500">Categoría</dt><dd class="font-medium text-gray-900">{{ $product->category->name }}</dd></div><div><dt class="text-gray-500">Unidad</dt><dd class="font-medium text-gray-900">{{ $product->unit->label() }}</dd></div><div><dt class="text-gray-500">Precio</dt><dd class="font-medium text-gray-900">Bs {{ number_format((float) $product->sale_price, 2, ',', '.') }}</dd></div><div><dt class="text-gray-500">Stock</dt><dd class="font-medium text-gray-900">{{ number_format((float) $product->stock, 3, ',', '.') }}</dd></div></dl>
                        <div class="mt-4 border-t border-gray-100 pt-4">@include('products.partials.actions')</div>
                    </article>
                @empty
                    <div class="rounded-xl bg-white p-8 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200 sm:col-span-2">No se encontraron productos.</div>
                @endforelse
            </div>
            <div class="mt-6">{{ $products->links() }}</div>
        </div>
    </div>
</x-app-layout>
