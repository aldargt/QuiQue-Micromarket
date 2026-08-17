<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        ¿Olvidaste tu contraseña? Indica tu correo electrónico y te enviaremos un enlace para restablecerla.
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('login') }}" class="ui-link text-sm">Volver a iniciar sesión</a>
            <x-primary-button>
                Enviar enlace para restablecer
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
