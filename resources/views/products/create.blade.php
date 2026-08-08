<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-gray-900">Crear producto</h1><p class="mt-1 text-sm text-gray-600">Registra la información inicial del producto.</p></x-slot>
    <div class="py-8"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('products.store') }}" class="space-y-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:p-8">
            @csrf
            @include('products.partials.form')
            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-end"><a href="{{ route('products.index') }}" class="inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a><x-primary-button>Crear producto</x-primary-button></div>
        </form>
    </div></div>
</x-app-layout>
