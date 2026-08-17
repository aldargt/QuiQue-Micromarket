<x-app-layout>
    <x-slot name="header"><div class="flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-bold tracking-tight text-gray-900">Venta {{ $sale->sale_number }}</h1><div class="flex flex-wrap items-center gap-3"><a href="{{ route('sales.index') }}" class="ui-link text-sm">Volver al historial</a><a href="{{ route('pos.index') }}" class="ui-button-primary">Nueva venta</a></div></div></x-slot>
    <div class="py-8" x-data="{ cancelOpen: {{ $errors->has('reason') ? 'true' : 'false' }} }"><div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('status') }}</div>@endif
        <section class="grid gap-3 bg-white p-5 shadow-sm sm:rounded-lg sm:grid-cols-4"><div><small class="text-gray-500">Fecha y hora</small><p>{{ $sale->confirmed_at->format('d/m/Y H:i') }}</p></div><div><small class="text-gray-500">Responsable</small><p>{{ $sale->user->name }}</p></div><div><small class="text-gray-500">Estado</small><p><span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $sale->status === App\Enums\SaleStatus::Cancelled ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">{{ $sale->status->label() }}</span></p></div><div><small class="text-gray-500">Forma de pago</small><p>{{ $sale->paymentLabel() }}</p></div></section>
        @if ($sale->status === App\Enums\SaleStatus::Cancelled)
            <section class="rounded-lg border border-red-200 bg-red-50 p-5 text-red-900"><h3 class="font-semibold">Venta anulada</h3><p class="mt-2"><span class="font-medium">Motivo:</span> {{ $sale->cancellation_reason }}</p><p class="mt-1 text-sm">Anulada por {{ $sale->cancelledBy->name }} el {{ $sale->cancelled_at->format('d/m/Y H:i') }}.</p></section>
        @endif
        <section class="overflow-x-auto bg-white shadow-sm sm:rounded-lg"><table class="min-w-full divide-y"><thead class="bg-gray-50"><tr>@foreach (['Producto','Cantidad','Precio unitario','Subtotal'] as $heading)<th class="px-4 py-3 text-left text-xs uppercase text-gray-500">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y">@foreach ($sale->items as $item)<tr><td class="px-4 py-3"><strong>{{ $item->product_name }}</strong><small class="block text-gray-500">{{ $item->product?->displayCode() }}</small></td><td class="px-4 py-3">{{ $item->quantityLabel() }}</td><td class="px-4 py-3">Bs {{ number_format((float) $item->unit_price, 2) }}</td><td class="px-4 py-3">Bs {{ number_format((float) $item->subtotal, 2) }}</td></tr>@endforeach</tbody><tfoot><tr><td colspan="3" class="px-4 py-4 text-right text-lg font-bold">Total</td><td class="px-4 py-4 text-lg font-bold">Bs {{ number_format((float) $sale->total, 2) }}</td></tr></tfoot></table></section>
        <section class="bg-white p-5 shadow-sm sm:rounded-lg"><h3 class="font-semibold mb-3">Pagos</h3><div class="space-y-2">@foreach ($sale->payments as $payment)<div class="flex justify-between"><span>{{ $payment->method->label() }}</span><strong>Bs {{ number_format((float) $payment->amount, 2) }}</strong></div>@if ($payment->method->value === 'cash' && $payment->received_amount !== null)<div class="flex justify-between text-sm text-gray-600"><span>Recibido: Bs {{ number_format((float) $payment->received_amount, 2) }}</span><span>Cambio: Bs {{ number_format((float) $payment->change_amount, 2) }}</span></div>@endif @endforeach</div></section>
        @if($sale->raffleParticipation)
            <section class="bg-white p-5 shadow-sm sm:rounded-lg">
                <h3 class="font-semibold">Participación en sorteo</h3>
                <p class="mt-1">{{ $sale->raffleParticipation->status->label() }}@if($sale->customer) · {{ $sale->customer->full_name }} · {{ $sale->raffleParticipation->tickets->count() }} ticket(s)@endif</p>
                @if($sale->raffleParticipation->tickets->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($sale->raffleParticipation->tickets as $ticket)
                            <span class="inline-flex items-center gap-2 rounded border px-2.5 py-1 font-mono text-sm {{ $sale->status === App\Enums\SaleStatus::Cancelled ? 'border-red-200 bg-red-50 text-red-800 opacity-80' : 'border-gray-200 bg-gray-100' }}">
                                <span class="{{ $sale->status === App\Enums\SaleStatus::Cancelled ? 'line-through decoration-red-500' : '' }}">{{ $ticket->ticket_number }}</span>
                                @if ($sale->status === App\Enums\SaleStatus::Cancelled)
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 font-sans text-[10px] font-bold uppercase tracking-wide text-red-800">Anulado</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
        @if ($sale->status === App\Enums\SaleStatus::Confirmed && $sale->confirmed_at->timezone(config('app.timezone'))->isSameDay(now(config('app.timezone'))))
            <div class="flex justify-end"><button type="button" @click="cancelOpen = true" class="rounded-md bg-red-700 px-4 py-2 font-semibold text-white hover:bg-red-800">Anular venta</button></div>
            <div x-cloak x-show="cancelOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4" role="dialog" aria-modal="true">
                <form method="POST" action="{{ route('sales.cancel', $sale) }}" class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">@csrf
                    <h3 class="text-lg font-semibold text-gray-900">Anular venta</h3>
                    <p class="mt-2 text-sm text-gray-600">La venta será anulada y las cantidades vendidas volverán al inventario. Esta operación no puede repetirse.</p>
                    <div class="mt-5"><x-input-label for="reason" value="Motivo de anulación"/><textarea id="reason" name="reason" rows="4" maxlength="500" required class="mt-1 block w-full rounded-md border-gray-300" placeholder="Ej.: Producto devuelto por el cliente">{{ old('reason') }}</textarea><x-input-error :messages="$errors->get('reason')" class="mt-2"/></div>
                    <div class="mt-6 flex justify-end gap-3"><button type="button" @click="cancelOpen = false" class="rounded-md border border-gray-300 px-4 py-2 font-semibold text-gray-700">Cancelar</button><button type="submit" class="rounded-md bg-red-700 px-4 py-2 font-semibold text-white">Confirmar anulación</button></div>
                </form>
            </div>
        @elseif ($sale->status === App\Enums\SaleStatus::Confirmed)
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">Esta venta ya no puede ser anulada porque corresponde a una fecha anterior.</div>
        @endif
    </div></div>
</x-app-layout>
