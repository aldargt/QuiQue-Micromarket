<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Editar cajero</h1>
            <p class="mt-1 text-sm text-gray-600">{{ $cashier->name }} · {{ $cashier->role->name }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.users.update', $cashier) }}" class="space-y-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:p-8">
                @csrf
                @method('PUT')
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Datos de la cuenta</h2>
                    <p class="mt-1 text-sm text-gray-500">El rol Cajero está protegido y no puede modificarse desde este formulario.</p>
                </div>
                @include('admin.users.partials.identity-fields')

                <fieldset>
                    <legend class="text-sm font-medium text-gray-700">Estado</legend>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3">
                            <input type="radio" name="is_active" value="1" class="text-cyan-600 focus:ring-cyan-500" @checked((string) old('is_active', (int) $cashier->is_active) === '1')>
                            <span><span class="block font-medium text-gray-900">Activo</span><span class="text-sm text-gray-500">Puede iniciar sesión.</span></span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3">
                            <input type="radio" name="is_active" value="0" class="text-cyan-600 focus:ring-cyan-500" @checked((string) old('is_active', (int) $cashier->is_active) === '0')>
                            <span><span class="block font-medium text-gray-900">Inactivo</span><span class="text-sm text-gray-500">No podrá iniciar nuevas sesiones.</span></span>
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                </fieldset>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('admin.users.index') }}" class="inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Volver</a>
                    <x-primary-button>Guardar cambios</x-primary-button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.users.password.update', $cashier) }}" class="space-y-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:p-8">
                @csrf
                @method('PUT')
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Restablecer contraseña</h2>
                    <p class="mt-1 text-sm text-gray-500">Introduce una contraseña temporal segura y comunícala directamente al cajero.</p>
                </div>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="password" value="Nueva contraseña" required />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="password_confirmation" value="Confirmar contraseña" required />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                    </div>
                </div>
                <div class="flex justify-end border-t border-gray-100 pt-6">
                    <x-primary-button>Restablecer contraseña</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
