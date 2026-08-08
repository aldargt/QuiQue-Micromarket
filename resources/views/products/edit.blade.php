<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-gray-900">Editar producto</h1><p class="mt-1 text-sm text-gray-600">{{ $product->name }} · {{ $product->internal_code }}</p></x-slot>
    <div class="py-8"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        @if (session('status'))<div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('products.update', $product) }}" class="space-y-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:p-8">
            @csrf @method('PUT')
            @include('products.partials.form')
            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-end"><a href="{{ route('products.index') }}" class="inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Volver</a><x-primary-button>Guardar cambios</x-primary-button></div>
        </form>
    </div></div>
</x-app-layout>
