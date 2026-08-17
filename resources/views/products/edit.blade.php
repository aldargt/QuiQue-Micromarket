<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-gray-900">Editar producto</h1><p class="mt-1 text-sm text-gray-600">{{ $product->name }} · {{ $product->internal_code }}</p></x-slot>
    <div class="py-8"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        @if (session('status'))<div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('products.update', $product) }}" class="space-y-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:p-8">
            @csrf @method('PUT')
            @if ($returnToPos)<input type="hidden" name="return_to" value="pos">@endif
            @include('products.partials.form')
            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-end"><a href="{{ $returnToPos ? route('pos.index') : route('products.index') }}" class="inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ $returnToPos ? 'Volver al punto de venta' : 'Volver' }}</a><x-primary-button>Guardar cambios</x-primary-button></div>
        </form>
        @if ($product->priceHistory->isNotEmpty())
            <section class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><div class="border-b p-5"><h2 class="font-semibold text-gray-900">Historial de precios</h2><p class="mt-1 text-sm text-gray-500">Cambios de precios de compra y venta del producto.</p></div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr>@foreach (['Fecha y hora', 'Responsable', 'Precio de compra', 'Precio de venta'] as $heading)<th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-gray-100">@foreach ($product->priceHistory as $change)<tr><td class="whitespace-nowrap px-4 py-3 text-sm">{{ $change->created_at->format('d/m/Y H:i') }}</td><td class="px-4 py-3 text-sm">{{ $change->user->name }}</td><td class="px-4 py-3 text-sm">@if ($change->old_purchase_price !== null)Bs {{ number_format((float) $change->old_purchase_price, 2, ',', '.') }} → Bs {{ number_format((float) $change->new_purchase_price, 2, ',', '.') }}@else<span class="text-gray-400">No disponible en este registro histórico</span>@endif</td><td class="px-4 py-3 text-sm">Bs {{ number_format((float) $change->old_price, 2, ',', '.') }} → Bs {{ number_format((float) $change->new_price, 2, ',', '.') }}</td></tr>@endforeach</tbody></table></div></section>
        @endif
    </div></div>
</x-app-layout>
