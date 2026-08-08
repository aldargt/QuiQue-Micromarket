<div class="grid gap-6 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <x-input-label for="name" value="Nombre completo" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', isset($cashier) ? $cashier->name : '')" required autofocus autocomplete="name" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="email" value="Correo electrónico" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', isset($cashier) ? $cashier->email : '')" required autocomplete="username" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="branch_id" value="Sucursal" />
        <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" required>
            <option value="">Seleccione una sucursal</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) old('branch_id', isset($cashier) ? $cashier->branch_id : '') === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
    </div>
</div>
