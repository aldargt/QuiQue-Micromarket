<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Auditoría</h2></x-slot>
    <div class="py-8"><div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">Registro de operaciones sensibles realizadas sobre productos y ventas.</div>
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>@foreach (['Fecha y hora', 'Usuario', 'Acción', 'Módulo', 'Registro', 'Detalle'] as $heading)<th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        @php
                            $isProduct = $log->auditable_type === App\Models\Product::class;
                            $module = $isProduct ? 'Producto' : 'Venta';
                            $record = $isProduct ? ($log->auditable?->name ?? 'Producto histórico') : ($log->auditable?->sale_number ?? 'Venta histórica');
                            $labels = ['purchase_price' => 'Precio de compra', 'sale_price' => 'Precio de venta', 'is_active' => 'Estado', 'status' => 'Estado', 'reason' => 'Motivo'];
                            $display = fn ($key, $value) => match ($key) {
                                'purchase_price', 'sale_price' => 'Bs '.number_format((float) $value, 2, ',', '.'),
                                'is_active' => $value ? 'Activo' : 'Inactivo',
                                'status' => $value === 'confirmed' ? 'Confirmada' : ($value === 'cancelled' ? 'Anulada' : ucfirst((string) $value)),
                                default => (string) $value,
                            };
                        @endphp
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-3 text-sm">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $log->user->name }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $log->action }}</td>
                            <td class="px-4 py-3 text-sm">{{ $module }}</td>
                            <td class="px-4 py-3 text-sm">{{ $record }}</td>
                            <td class="min-w-64 px-4 py-3 text-sm text-gray-700">
                                @foreach (($log->new_values ?? []) as $key => $newValue)
                                    <div><span class="font-medium">{{ $labels[$key] ?? ucfirst(str_replace('_', ' ', $key)) }}:</span>
                                        @if (array_key_exists($key, $log->old_values ?? [])){{ $display($key, $log->old_values[$key]) }} → @endif{{ $display($key, $newValue) }}
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-sm text-gray-500">Todavía no existen operaciones auditadas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div></div>
</x-app-layout>
