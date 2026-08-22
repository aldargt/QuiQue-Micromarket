@php($limitedEdit = isset($product) && Auth::user()->hasAnyRole(['cashier']))
@if ($limitedEdit)
    <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Como Cajero, puede modificar los precios, la fecha de vencimiento y el stock mínimo. Los demás datos se muestran solo como referencia.</div>
@endif
<div class="grid gap-6 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <x-input-label for="name" value="Nombre del producto" :required="! isset($product)" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full disabled:bg-gray-100" :value="old('name', isset($product) ? $product->name : '')" :disabled="$limitedEdit" required autofocus maxlength="150" autocomplete="off" placeholder="Ej.: Leche Pil 1L" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="barcode" value="Código de barras (opcional)" />
        <div class="mt-1 flex flex-col gap-2 sm:flex-row">
            <x-text-input id="barcode" name="barcode" type="text" inputmode="numeric" class="block w-full disabled:bg-gray-100" :value="old('barcode', isset($product) ? $product->barcode : '')" :disabled="$limitedEdit" maxlength="14" autocomplete="off" placeholder="Ej.: 7771234567890" />
            @unless (isset($product))
                <button type="button" data-barcode-scanner-open class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg border border-[#2EB8CE] bg-white px-4 py-2 text-sm font-semibold text-[#16788a] transition hover:bg-cyan-50 focus:outline-none focus:ring-2 focus:ring-[#2EB8CE] focus:ring-offset-2 active:bg-cyan-100 dark:bg-slate-800 dark:text-cyan-300 dark:hover:bg-slate-700">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 5 13 3h-2L9.5 5H6a3 3 0 0 0-3 3v8a3 3 0 0 0 3 3h12a3 3 0 0 0 3-3V8a3 3 0 0 0-3-3h-3.5Z"/><circle cx="12" cy="12" r="3.25"/></svg>
                    Escanear código
                </button>
            @endunless
        </div>
        <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
        <p class="mt-2 text-xs text-gray-500">Debe contener entre 8 y 14 dígitos. El código interno se genera automáticamente.</p>
    </div>
    <div>
        <x-input-label for="category_id" value="Categoría" :required="! isset($product)" />
        <select id="category_id" name="category_id" class="select-placeholder mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 disabled:bg-gray-100" @disabled($limitedEdit) required>
            <option value="">Seleccione una categoría</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', isset($product) ? $product->category_id : '') === (string) $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (inactiva, relación actual)' }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="unit" value="Unidad de medida" :required="! isset($product)" />
        <select id="unit" name="unit" class="select-placeholder mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 disabled:bg-gray-100" onchange="document.getElementById('minimum_stock').step = this.value === 'unit' ? '1' : '0.001'" @disabled($limitedEdit) required>
            <option value="">Seleccione una unidad</option>
            @foreach ($units as $unit)
                <option value="{{ $unit->value }}" @selected(old('unit', isset($product) ? $product->unit->value : '') === $unit->value)>{{ $unit->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('unit')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="expires_at" value="Fecha de vencimiento (opcional)" />
        <x-text-input id="expires_at" name="expires_at" type="date" class="mt-1 block w-full disabled:bg-gray-100" :value="old('expires_at', isset($product) ? $product->expires_at?->format('Y-m-d') : '')" />
        <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="purchase_price" value="Precio de compra (Bs)" />
        <x-text-input id="purchase_price" name="purchase_price" type="number" min="0" step="any" class="mt-1 block w-full" :value="old('purchase_price', isset($product) ? $product->purchase_price : '0.00')" required />
        <x-input-error :messages="$errors->get('purchase_price')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="sale_price" value="Precio de venta (Bs)" :required="! isset($product)" />
        <x-text-input id="sale_price" name="sale_price" type="number" min="0" step="any" class="mt-1 block w-full" :value="old('sale_price', isset($product) ? $product->sale_price : '')" required />
        <x-input-error :messages="$errors->get('sale_price')" class="mt-2" />
    </div>
    <div class="rounded-lg border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900">
        <span class="font-semibold">Stock actual:</span>
        {{ isset($product) ? $product->unit->formatQuantity($product->stock) : '0 (stock inicial)' }}
        <p class="mt-1 text-xs">El stock se modificará exclusivamente desde el módulo de Inventario.</p>
    </div>
    <div>
        <x-input-label for="minimum_stock" value="Stock mínimo" />
        <x-text-input id="minimum_stock" name="minimum_stock" type="number" min="0" :step="isset($product) && $product->unit === App\Enums\MeasurementUnit::Unit ? '1' : '0.001'" class="mt-1 block w-full disabled:bg-gray-100" :value="old('minimum_stock', isset($product) ? $product->unit->formatInputQuantity($product->minimum_stock) : '0')" required />
        <x-input-error :messages="$errors->get('minimum_stock')" class="mt-2" />
    </div>
</div>

@unless (isset($product))
    <div data-barcode-scanner-modal class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="barcode-scanner-title">
        <button type="button" data-barcode-scanner-cancel class="absolute inset-0 h-full w-full cursor-default bg-slate-950/75" aria-label="Cerrar escáner"></button>
        <div class="relative mx-auto flex min-h-full max-w-lg items-center px-4 py-6">
            <div class="w-full overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/10 dark:bg-slate-800 dark:ring-white/10">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-slate-700">
                    <h2 id="barcode-scanner-title" class="text-lg font-semibold text-gray-900 dark:text-slate-100">Escanear código de barras</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Utilice la cámara trasera y mantenga el producto estable.</p>
                </div>
                <div class="space-y-4 p-5">
                    <div class="relative aspect-[4/3] overflow-hidden rounded-xl bg-slate-950">
                        <video data-barcode-scanner-video class="h-full w-full object-cover" playsinline muted></video>
                        <div class="pointer-events-none absolute inset-x-6 top-1/2 h-28 -translate-y-1/2 rounded-lg border-2 border-cyan-300 shadow-[0_0_0_999px_rgba(0,0,0,0.28)]" aria-hidden="true"></div>
                    </div>
                    <p data-barcode-scanner-status class="text-center text-sm text-gray-600 dark:text-slate-300" aria-live="polite"></p>
                    <p data-barcode-scanner-error class="hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200" role="alert"></p>
                    <div class="flex justify-end gap-3">
                        <button type="button" data-barcode-scanner-cancel class="cursor-pointer rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Cancelar</button>
                        <button type="button" data-barcode-scanner-retry class="hidden cursor-pointer rounded-lg bg-[#2EB8CE] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#259aae] focus:outline-none focus:ring-2 focus:ring-[#2EB8CE] focus:ring-offset-2 active:bg-[#208799]">Reintentar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endunless
