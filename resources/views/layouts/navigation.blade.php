<nav x-data="backupUi()" class="bg-white border-b border-gray-100">
    @php
        $profileFirstName = \Illuminate\Support\Str::before(trim(Auth::user()->name), ' ');
        $profileRoleLabel = Auth::user()->role->slug === 'administrator' ? 'Administración' : 'Cajas';
    @endphp
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Inicio
                    </x-nav-link>
                    <x-nav-link :href="route('pos.index')" :active="request()->routeIs('pos.*')">
                        Punto de venta
                    </x-nav-link>
                    <x-nav-link :href="route('sales.index')" :active="request()->routeIs('sales.*')">
                        Ventas
                    </x-nav-link>
                    <x-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">Clientes y tickets</x-nav-link>
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                        Reportes
                    </x-nav-link>
                    @if (Auth::user()->hasAnyRole(['administrator']))
                        <x-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">Categorías</x-nav-link>
                    @endif
                    <x-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">
                        Productos
                    </x-nav-link>
                    <x-nav-link :href="route('inventory.index')" :active="request()->routeIs('inventory.*')">
                        Inventario
                    </x-nav-link>
                    @if (Auth::user()->hasAnyRole(['administrator']))
                        <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                            Usuarios
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-3 py-2 border border-transparent rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <span class="text-left leading-tight">
                                <span class="block text-sm font-semibold text-gray-700">{{ $profileFirstName }}</span>
                                <span class="block text-xs font-normal text-gray-500">{{ $profileRoleLabel }}</span>
                            </span>

                            <div>
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            Mi perfil
                        </x-dropdown-link>

                        @if (Auth::user()->hasAnyRole(['administrator']))
                            <x-dropdown-link :href="route('admin.audit.index')">Auditoría</x-dropdown-link>
                            <button type="button" @click="openBackup" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none">Realizar backup</button>
                        @endif

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                Cerrar sesión
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                Inicio
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('pos.index')" :active="request()->routeIs('pos.*')">
                Punto de venta
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('sales.index')" :active="request()->routeIs('sales.*')">
                Ventas
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">Clientes y tickets</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                Reportes
            </x-responsive-nav-link>
            @if (Auth::user()->hasAnyRole(['administrator']))
                <x-responsive-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">Categorías</x-responsive-nav-link>
            @endif
            <x-responsive-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">
                Productos
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('inventory.index')" :active="request()->routeIs('inventory.*')">
                Inventario
            </x-responsive-nav-link>
            @if (Auth::user()->hasAnyRole(['administrator']))
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    Usuarios
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ $profileFirstName }}</div>
                <div class="font-medium text-sm text-gray-600">{{ $profileRoleLabel }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    Mi perfil
                </x-responsive-nav-link>

                @if (Auth::user()->hasAnyRole(['administrator']))
                    <x-responsive-nav-link :href="route('admin.audit.index')">Auditoría</x-responsive-nav-link>
                    <button type="button" @click="openBackup" class="block w-full px-4 py-2 text-start text-base font-medium text-gray-600 hover:bg-gray-50">Realizar backup</button>
                @endif

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        Cerrar sesión
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>

    @if (Auth::user()->hasAnyRole(['administrator']))
        <div x-cloak x-show="backupOpen" x-transition.opacity class="fixed inset-0 z-[70] flex items-center justify-center bg-gray-900/60 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <template x-if="backupPhase === 'confirm'"><div><h3 class="text-lg font-semibold text-gray-900">Realizar backup</h3><p class="mt-3 text-sm text-gray-600">¿Está seguro de que desea realizar una copia de seguridad del sistema?</p><div class="mt-6 flex justify-end gap-3"><button type="button" @click="backupOpen=false" class="rounded-md border border-gray-300 px-4 py-2 font-semibold text-gray-700">Cancelar</button><button type="button" @click="runBackup" class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white">Sí, realizar backup</button></div></div></template>
                <template x-if="backupPhase === 'loading'"><div class="py-4 text-center"><svg class="mx-auto h-10 w-10 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg><h3 class="mt-4 text-lg font-semibold">Realizando copia de seguridad</h3><p class="mt-2 text-sm text-gray-600">Estamos realizando la copia de seguridad del sistema. Por favor, espere.</p></div></template>
                <template x-if="backupPhase === 'result'"><div><h3 class="text-lg font-semibold" x-text="backupResult.title"></h3><p class="mt-3 whitespace-pre-line text-sm text-gray-600" x-text="backupResult.message"></p><div class="mt-6 flex justify-end"><button type="button" @click="backupOpen=false" class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white">Aceptar</button></div></div></template>
            </div>
        </div>
    @endif
</nav>
<style>[x-cloak] { display: none !important; }</style>
<script>
    function backupUi() { return { open: false, backupOpen: false, backupPhase: 'confirm', backupResult: {}, openBackup() { this.backupPhase='confirm'; this.backupOpen=true; }, async runBackup() { if(this.backupPhase==='loading') return; this.backupPhase='loading'; try { const response=await fetch(@js(route('admin.backup.store')), {method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':@js(csrf_token())}}); this.backupResult=response.ok?await response.json():{title:'No se pudo realizar el backup',message:'No fue posible generar el respaldo local.'}; } catch(error) { this.backupResult={title:'No se pudo realizar el backup',message:'No fue posible generar el respaldo local.'}; } this.backupPhase='result'; } } }
</script>
