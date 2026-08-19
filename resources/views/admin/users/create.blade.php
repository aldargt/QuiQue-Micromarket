<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-gray-900">Crear cajero</h1>
        <p class="mt-1 text-sm text-gray-600">La cuenta se creará activa y con rol Cajero.</p>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:p-8">
                @csrf
                @include('admin.users.partials.identity-fields', ['showRequired' => true])

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="password" value="Contraseña" required />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="password_confirmation" value="Confirmar contraseña" required />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('admin.users.index') }}" class="inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                    <x-primary-button>Crear cajero</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
