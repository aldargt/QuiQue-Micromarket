<div>
    <x-input-label for="name" value="Nombre" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', isset($category) ? $category->name : '')" required autofocus maxlength="100" autocomplete="off" placeholder="Ej.: Bebidas" />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
    <p class="mt-2 text-sm text-gray-500">Usa un nombre breve y claro. No puede repetirse dentro de la sucursal.</p>
</div>
