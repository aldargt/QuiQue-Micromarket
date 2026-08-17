<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Antes de continuar, verifica tu correo electrónico mediante el enlace que acabamos de enviarte. Si no lo recibiste, podemos enviarte otro.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            Se envió un nuevo enlace de verificación a tu correo electrónico.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    Reenviar correo de verificación
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="rounded-md text-sm font-semibold text-cyan-700 hover:text-cyan-900 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2">
                Cerrar sesión
            </button>
        </form>
    </div>
</x-guest-layout>
