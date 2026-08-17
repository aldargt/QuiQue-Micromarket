<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">Clientes y tickets</h2>
            @if(auth()->user()->hasAnyRole(['administrator']))
                <button type="button" x-data @click="$dispatch('open-raffle-settings')" class="ui-button-primary">Configurar umbral de tickets</button>
            @endif
        </div>
    </x-slot>
    <div class="py-8" x-data="{ settingsOpen: @js($errors->has('raffle_ticket_threshold')) }" @open-raffle-settings.window="settingsOpen = true" @keydown.escape.window="settingsOpen = false">
        <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if(session('status'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('status') }}</div>@endif
            <form class="flex gap-2"><x-text-input name="search" value="{{ $search }}" class="w-full" placeholder="Buscar por nombre, teléfono o CI"/><x-primary-button>Buscar</x-primary-button></form>
            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg"><table class="min-w-full divide-y"><thead class="bg-gray-50"><tr>@foreach(['Cliente','Teléfono','CI','Tickets',''] as $h)<th class="px-4 py-3 text-left text-xs uppercase text-gray-500">{{ $h }}</th>@endforeach</tr></thead><tbody class="divide-y">@forelse($customers as $customer)<tr><td class="px-4 py-3 font-medium">{{ $customer->full_name }}</td><td class="px-4 py-3">{{ $customer->phone }}</td><td class="px-4 py-3">{{ $customer->ci ?: 'No registrado' }}</td><td class="px-4 py-3">{{ $customer->tickets_count }}</td><td class="px-4 py-3"><a class="text-indigo-600" href="{{ route('customers.show', $customer) }}">Ver historial</a></td></tr>@empty<tr><td colspan="5" class="p-6 text-center text-gray-500">No se encontraron clientes.</td></tr>@endforelse</tbody></table></div>{{ $customers->links() }}
        </div>
        @if(auth()->user()->hasAnyRole(['administrator']))
            <div x-cloak x-show="settingsOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4" role="dialog" aria-modal="true" aria-labelledby="raffle-settings-title">
                <form method="POST" action="{{ route('admin.raffle-settings.update') }}" @click.outside="settingsOpen=false" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    @csrf @method('PATCH')
                    <h3 id="raffle-settings-title" class="text-lg font-semibold">Configurar umbral de tickets</h3>
                    <div class="mt-5"><x-input-label for="raffle-threshold" value="Monto requerido por ticket"/><div class="mt-1 flex items-center gap-2"><span class="font-semibold">Bs</span><x-text-input id="raffle-threshold" name="raffle_ticket_threshold" type="number" min="1" step="1" value="{{ old('raffle_ticket_threshold', (int) $branch->raffle_ticket_threshold) }}" class="w-full" required/></div><x-input-error :messages="$errors->get('raffle_ticket_threshold')" class="mt-2"/></div>
                    <p class="mt-3 text-sm text-gray-600">Se aplicará sólo a ventas nuevas; el historial conservará sus valores.</p>
                    <div class="mt-6 flex justify-end gap-3"><button type="button" @click="settingsOpen=false" class="rounded-md border px-4 py-2 font-semibold text-gray-700">Cancelar</button><x-primary-button>Guardar configuración</x-primary-button></div>
                </form>
            </div>
        @endif
    </div>
    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
