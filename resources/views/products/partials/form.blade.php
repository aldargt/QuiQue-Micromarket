@php($limitedEdit = isset($product) && Auth::user()->hasAnyRole(['cashier']))
@if ($limitedEdit)
    <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Como Cajero, puede modificar únicamente los precios de compra y venta. Los demás datos se muestran solo como referencia.</div>
@endif
<div class="grid gap-6 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <x-input-label for="name" value="Nombre del producto" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full disabled:bg-gray-100" :value="old('name', isset($product) ? $product->name : '')" :disabled="$limitedEdit" required autofocus maxlength="150" placeholder="Ej.: Leche Pil 1L" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="barcode" value="Código de barras (opcional)" />
        <x-text-input id="barcode" name="barcode" type="text" inputmode="numeric" class="mt-1 block w-full disabled:bg-gray-100" :value="old('barcode', isset($product) ? $product->barcode : '')" :disabled="$limitedEdit" maxlength="14" placeholder="Ej.: 7771234567890" />
        <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
        <p class="mt-2 text-xs text-gray-500">Debe contener entre 8 y 14 dígitos. El código interno se genera automáticamente.</p>
    </div>
    <div>
        <x-input-label for="category_id" value="Categoría" />
        <select id="category_id" name="category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 disabled:bg-gray-100" @disabled($limitedEdit) required>
            <option value="">Seleccione una categoría</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', isset($product) ? $product->category_id : '') === (string) $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (inactiva, relación actual)' }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="unit" value="Unidad de medida" />
        <select id="unit" name="unit" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 disabled:bg-gray-100" onchange="document.getElementById('minimum_stock').step = this.value === 'unit' ? '1' : '0.001'" @disabled($limitedEdit) required>
            <option value="">Seleccione una unidad</option>
            @foreach ($units as $unit)
                <option value="{{ $unit->value }}" @selected(old('unit', isset($product) ? $product->unit->value : '') === $unit->value)>{{ $unit->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('unit')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="expires_at" value="Fecha de vencimiento (opcional)" />
        <x-text-input id="expires_at" name="expires_at" type="date" class="mt-1 block w-full disabled:bg-gray-100" :value="old('expires_at', isset($product) ? $product->expires_at?->format('Y-m-d') : '')" :disabled="$limitedEdit" />
        <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="purchase_price" value="Precio de compra (Bs)" />
        <x-text-input id="purchase_price" name="purchase_price" type="number" min="0" step="any" class="mt-1 block w-full" :value="old('purchase_price', isset($product) ? $product->purchase_price : '0.00')" required />
        <x-input-error :messages="$errors->get('purchase_price')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="sale_price" value="Precio de venta (Bs)" />
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
        <x-text-input id="minimum_stock" name="minimum_stock" type="number" min="0" :step="isset($product) && $product->unit === App\Enums\MeasurementUnit::Unit ? '1' : '0.001'" class="mt-1 block w-full disabled:bg-gray-100" :value="old('minimum_stock', isset($product) ? $product->unit->formatInputQuantity($product->minimum_stock) : '0')" :disabled="$limitedEdit" required />
        <x-input-error :messages="$errors->get('minimum_stock')" class="mt-2" />
    </div>
</div>
