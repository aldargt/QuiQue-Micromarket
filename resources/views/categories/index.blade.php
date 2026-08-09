<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Categorías</h1>
                <p class="mt-1 text-sm text-gray-600">Organiza los productos de {{ Auth::user()->branch?->name ?? 'tu sucursal' }}.</p>
            </div>
            @if (Auth::user()->hasAnyRole(['administrator']))
                <a href="{{ route('categories.create') }}" class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2">
                    Crear categoría
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('categories.index') }}" class="mb-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:flex sm:items-end sm:gap-3">
                <div class="flex-1">
                    <x-input-label for="search" value="Buscar categoría" />
                    <x-text-input id="search" name="search" type="search" class="mt-1 block w-full" :value="$search" placeholder="Ej.: Bebidas" />
                </div>
                <div class="mt-3 flex gap-2 sm:mt-0">
                    <x-primary-button>Buscar</x-primary-button>
                    @if ($search !== '')
                        <a href="{{ route('categories.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Limpiar</a>
                    @endif
                </div>
            </form>

            <div class="hidden overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 md:block">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Creada</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($categories as $category)
                            <tr>
                                <td class="px-6 py-4 font-medium text-gray-900">{{ $category->name }}</td>
                                <td class="px-6 py-4">@include('categories.partials.status')</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $category->created_at->format('d/m/Y') }}</td>
                                <td class="px-6 py-4">@if (Auth::user()->hasAnyRole(['administrator'])) @include('categories.partials.actions') @else <span class="text-sm text-gray-400">Solo consulta</span> @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No se encontraron categorías.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="space-y-3 md:hidden">
                @forelse ($categories as $category)
                    <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div class="flex items-start justify-between gap-3">
                            <div><h2 class="font-semibold text-gray-900">{{ $category->name }}</h2><p class="mt-1 text-xs text-gray-500">Creada el {{ $category->created_at->format('d/m/Y') }}</p></div>
                            @include('categories.partials.status')
                        </div>
                        @if (Auth::user()->hasAnyRole(['administrator']))<div class="mt-4 border-t border-gray-100 pt-4">@include('categories.partials.actions')</div>@endif
                    </article>
                @empty
                    <div class="rounded-xl bg-white p-8 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">No se encontraron categorías.</div>
                @endforelse
            </div>

            <div class="mt-6">{{ $categories->links() }}</div>
        </div>
    </div>
</x-app-layout>
