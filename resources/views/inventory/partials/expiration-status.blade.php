@if (! $product->expires_at)
    <span class="text-gray-400">Sin vencimiento</span>
@elseif ($product->expires_at->lt(today()))
    <span class="font-semibold text-red-700">Vencido · {{ $product->expires_at->format('d/m/Y') }}</span>
@elseif ($product->expires_at->lte(today()->addDays(7)))
    <span class="font-semibold text-amber-700">Próximo · {{ $product->expires_at->format('d/m/Y') }}</span>
@else
    <span class="text-green-700">Vigente · {{ $product->expires_at->format('d/m/Y') }}</span>
@endif
