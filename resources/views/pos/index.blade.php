<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Punto de venta</h2></x-slot>

    <div class="py-8" x-data="pos()" @keydown.escape.window="closeModals()">
        <form method="POST" action="{{ route('pos.sales.store') }}" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" @submit="submitting = true">
            @csrf
            @if (session('status'))<div class="mb-5 rounded-md bg-green-50 p-4 text-green-800">{{ session('status') }}</div>@endif
            <div class="grid gap-6 lg:grid-cols-5">
                <section class="lg:col-span-3 space-y-4">
                    <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                        <label for="product-search" class="font-medium text-gray-800">Buscar o escanear producto</label>
                        <input id="product-search" x-model="query" @input.debounce.250ms="search" @keydown.enter.prevent="addFirst" autofocus autocomplete="off" placeholder="Nombre, código de barras o código interno" class="mt-2 block w-full rounded-md border-gray-300">
                        <p class="mt-1 text-sm text-gray-500">Escriba un nombre o escanee el código y presione Enter.</p>
                        <div x-show="results.length" class="mt-3 divide-y rounded-md border">
                            <template x-for="product in results" :key="product.id">
                                <button type="button" @click="add(product)" class="flex w-full justify-between gap-4 p-3 text-left hover:bg-gray-50">
                                    <span><strong x-text="product.name"></strong><small class="block text-gray-500" x-text="product.code + ' · ' + product.unit"></small></span>
                                    <span class="text-right"><strong x-text="money(product.price)"></strong><small class="block text-gray-500" x-text="'Stock: ' + product.stock_label"></small></span>
                                </button>
                            </template>
                        </div>
                        <p x-show="searched && !results.length" class="mt-3 text-sm text-gray-600">No se encontraron productos activos con stock.</p>
                    </div>

                    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="flex items-center justify-between gap-4 p-5 border-b"><h3 class="font-semibold">Productos de la venta</h3><button type="button" x-show="cart.length" @click="openClearModal" class="text-sm font-semibold text-red-600 hover:text-red-800">Vaciar carrito</button></div>
                        <p x-show="!cart.length" class="p-8 text-center text-gray-500">Busque un producto para comenzar.</p>
                        <div class="divide-y">
                            <template x-for="(item, index) in cart" :key="item.id">
                                <div class="p-4 grid gap-3 sm:grid-cols-[1fr_9rem_7rem_5.5rem] sm:items-center">
                                    <div><strong x-text="item.name"></strong><div class="text-sm text-gray-500"><span x-text="`Precio usado: ${money(effectivePrice(item))}`"></span><span x-text="` / ${item.unit} · Disponible: ${item.stock_label}`"></span></div><div x-show="item.price_changed" class="mt-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"><p class="font-semibold">El precio de este producto cambió.</p><p class="mt-1">Nuevo precio: <strong x-text="money(item.price)"></strong></p><p class="mt-1">Actualice el carrito para continuar.</p><button type="button" @click="acknowledgePrice(item)" class="mt-2 font-semibold text-amber-950 underline">Actualizar carrito</button></div><div x-show="!item.available" class="mt-2 text-sm font-semibold text-red-600">Este producto ya no está disponible.</div></div>
                                    <div>
                                        <label class="sr-only">Cantidad</label>
                                        <input type="number" :step="item.is_unit ? '1' : 'any'" :min="item.is_unit ? '1' : '0.001'" :max="item.stock" x-model="item.quantity" @input="validateItem(item)" @change="sync(item)" class="w-full rounded-md border-gray-300">
                                        <input type="hidden" :name="`items[${index}][product_id]`" :value="item.id">
                                        <input type="hidden" :name="`items[${index}][quantity]`" :value="item.quantity">
                                        <small x-show="item.error" class="text-red-600" x-text="item.error"></small>
                                    </div>
                                    <strong class="text-right" x-text="money(lineTotal(item))"></strong>
                                    <div class="flex justify-end gap-2"><a :href="`{{ url('/products') }}/${item.id}/edit?return=pos`" class="inline-flex h-10 w-10 items-center justify-center rounded-md text-indigo-600 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Editar producto" title="Editar producto"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 1 1 3.182 3.182L8.25 18.463 3 19.875l1.412-5.25 12.45-11.138Z"/><path stroke-linecap="round" d="M15.75 4.5 19.5 8.25"/></svg></a><button type="button" @click="remove(index)" class="inline-flex h-10 w-10 items-center justify-center rounded-md text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500" aria-label="Eliminar producto del carrito" title="Eliminar producto del carrito"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14M10 10v6m4-6v6"/></svg></button></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>

                <aside class="lg:col-span-2">
                    <div class="space-y-4 bg-white p-5 shadow-sm sm:rounded-lg lg:sticky lg:top-4">
                        <div class="flex justify-between text-2xl font-bold"><span>Total</span><span x-text="money(total)"></span></div>
                        <div>
                            <label class="font-medium">Forma de pago</label>
                            <select name="payment_type" x-model="payment" class="mt-2 block w-full rounded-md border-gray-300">
                                <option value="cash">Efectivo</option><option value="qr">QR</option><option value="mixed">Mixto</option>
                            </select>
                        </div>
                        <div x-show="payment === 'cash'">
                            <label for="cash_received" class="font-medium">Efectivo recibido</label>
                            <input id="cash_received" name="cash_received" type="number" step="any" min="0" x-model="cashReceived" class="mt-2 block w-full rounded-md border-gray-300">
                            <p class="mt-2 text-sm">Cambio: <strong x-text="money(change)"></strong></p>
                        </div>
                        <div x-show="payment === 'mixed'" class="space-y-3">
                            <div><label for="cash_amount" class="font-medium">Monto en efectivo</label><input id="cash_amount" name="cash_amount" type="number" step="any" min="0" x-model="cashAmount" class="mt-1 block w-full rounded-md border-gray-300"></div>
                            <div><label for="qr_amount" class="font-medium">Monto por QR</label><input id="qr_amount" name="qr_amount" type="number" step="any" min="0" x-model="qrAmount" class="mt-1 block w-full rounded-md border-gray-300"></div>
                            <p class="text-sm">Pagado: <strong x-text="money(Number(cashAmount || 0) + Number(qrAmount || 0))"></strong></p>
                        </div>
                        <div x-cloak x-show="raffleTickets > 0" class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <template x-if="raffleDecision === ''"><div><p class="font-semibold text-amber-950" x-text="`Esta venta genera ${raffleTickets} ${raffleTickets === 1 ? 'ticket' : 'tickets'} para el sorteo.`"></p><p class="text-sm text-gray-700">¿El cliente desea participar?</p><div class="mt-2 flex gap-2"><button type="button" @click="openRaffleModal" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Participar</button><button type="button" @click="declineRaffle" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700">No participar</button></div></div></template>
                            <template x-if="raffleDecision === 'decline'"><div><p class="text-sm font-medium text-gray-800">El cliente no desea participar en esta venta.</p><button type="button" @click="changeRaffleDecision" class="mt-1 text-sm font-semibold text-indigo-700 underline">Cambiar decisión</button></div></template>
                            <template x-if="raffleDecision === 'participate'"><div><p class="font-semibold text-green-800" x-text="`Participará: ${selectedCustomer ? selectedCustomer.full_name : customerFullName}`"></p><p class="text-sm text-gray-600" x-text="`${raffleTickets} ${raffleTickets === 1 ? 'ticket será asignado' : 'tickets serán asignados'} al confirmar la venta.`"></p><button type="button" @click="openRaffleModal" class="mt-1 text-sm font-semibold text-indigo-700 underline">Cambiar cliente</button></div></template>
                            <input type="hidden" name="raffle_decision" :value="raffleDecision">
                            <input type="hidden" name="customer_id" :value="selectedCustomer?.id || ''">
                            <input type="hidden" name="customer_full_name" :value="selectedCustomer ? '' : customerFullName">
                            <input type="hidden" name="customer_phone" :value="selectedCustomer ? '' : customerPhone">
                            <input type="hidden" name="customer_ci" :value="selectedCustomer ? '' : customerCi">
                        </div>
                        @if ($errors->any())<div class="rounded-md bg-red-50 p-3 text-sm text-red-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                        <button type="submit" :disabled="!canSubmit || submitting" class="w-full rounded-md bg-indigo-600 px-4 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" x-text="submitting ? 'Confirmando…' : 'Confirmar venta'"></button>
                    </div>
                </aside>
            </div>
        </form>

        <div x-cloak x-show="raffleModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4" role="dialog" aria-modal="true" aria-labelledby="raffle-modal-title">
            <div @click.outside="raffleModalOpen = false" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between gap-4"><div><h3 id="raffle-modal-title" class="text-lg font-semibold">Identificar cliente participante</h3><p class="mt-1 text-sm text-gray-600" x-text="`Se asignarán ${raffleTickets} ${raffleTickets === 1 ? 'ticket' : 'tickets'}.`"></p></div><button type="button" @click="raffleModalOpen=false" class="text-2xl text-gray-400" aria-label="Cerrar">&times;</button></div>
                <div class="mt-5 flex gap-2 border-b"><button type="button" @click="customerMode='search'" :class="customerMode === 'search' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500'" class="border-b-2 px-3 py-2 font-semibold">Buscar existente</button><button type="button" @click="startNewCustomer" :class="customerMode === 'new' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500'" class="border-b-2 px-3 py-2 font-semibold">Registrar nuevo cliente</button></div>
                <div x-show="customerMode === 'search'" class="mt-5 space-y-3">
                    <x-text-input x-model="customerQuery" @input.debounce.250ms="searchCustomers" class="w-full" placeholder="Escriba nombre, teléfono o CI" autocomplete="off"/>
                    <div x-show="customerResults.length" class="divide-y rounded-md border"><template x-for="customer in customerResults" :key="customer.id"><button type="button" @click="selectCustomer(customer)" class="block w-full p-3 text-left hover:bg-gray-50"><strong x-text="customer.full_name"></strong><span class="block text-sm text-gray-500" x-text="customer.phone + (customer.ci ? ' · CI '+customer.ci : '')"></span></button></template></div>
                    <div x-show="customerSearched && !customerResults.length && !selectedCustomer" class="rounded-md bg-gray-50 p-4 text-sm text-gray-700">No se encontraron coincidencias. <button type="button" @click="startNewCustomer" class="font-semibold text-indigo-700 underline">Registrar nuevo cliente</button></div>
                    <div x-show="selectedCustomer" class="rounded-md border border-green-200 bg-green-50 p-4"><p class="font-semibold" x-text="selectedCustomer?.full_name"></p><p class="text-sm" x-text="`Teléfono: ${selectedCustomer?.phone || ''}`"></p><p x-show="selectedCustomer?.ci" class="text-sm" x-text="`CI: ${selectedCustomer?.ci}`"></p><button type="button" @click="confirmExistingCustomer" class="mt-4 rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white">Asignar tickets</button></div>
                </div>
                <div x-show="customerMode === 'new'" class="mt-5 space-y-4">
                    <div><x-input-label value="Nombre completo *"/><x-text-input x-model="customerFullName" class="mt-1 w-full"/></div><div><x-input-label value="Teléfono/celular *"/><x-text-input x-model="customerPhone" class="mt-1 w-full"/></div><div><x-input-label value="CI (opcional)"/><x-text-input x-model="customerCi" class="mt-1 w-full"/></div>
                    <p x-show="customerFormError" class="text-sm text-red-600" x-text="customerFormError"></p><button type="button" @click="confirmNewCustomer" class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white">Registrar y asignar tickets</button>
                </div>
            </div>
        </div>

        <div x-cloak x-show="clearModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4" role="dialog" aria-modal="true" aria-labelledby="clear-cart-title">
            <div @click.outside="clearModalOpen = false" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 id="clear-cart-title" class="text-lg font-semibold text-gray-900">¿Vaciar carrito?</h3>
                <p class="mt-3 text-sm text-gray-600">Se eliminarán todos los productos actualmente agregados al carrito.</p>
                <p class="mt-2 text-sm text-gray-600">Esto no realizará ninguna venta ni modificará el inventario.</p>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" @click="clearModalOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="button" @click="clearCart" :disabled="clearingCart" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50" x-text="clearingCart ? 'Vaciando…' : 'Vaciar carrito'"></button>
                </div>
            </div>
        </div>
    </div>
    <style>[x-cloak] { display: none !important; }</style>
    <script>
        function pos() { return {
            query: '', results: [], searched: false, cart: @js($initialCart), payment: 'cash', cashReceived: '', cashAmount: '', qrAmount: '', submitting: false, clearModalOpen: false, clearingCart: false, raffleTickets: @js($initialRaffleQuote), raffleDecision: '', raffleModalOpen: false, customerMode: 'search', customerQuery: '', customerResults: [], customerSearched: false, selectedCustomer: null, customerFullName: '', customerPhone: '', customerCi: '', customerFormError: '',
            async search() { this.searched = this.query.trim().length > 0; if (!this.searched) { this.results = []; return; } const response = await fetch(`{{ route('pos.products.search') }}?search=${encodeURIComponent(this.query)}`, {headers: {'Accept': 'application/json'}}); this.results = await response.json(); },
            addFirst() { if (this.results.length) this.add(this.results[0]); },
            async add(product) { let item = this.cart.find(i => i.id === product.id); if (item) item.quantity = String(Number(item.quantity) + 1); else { item = {...product, quantity: '1', observed_price: product.price, price_changed: false, available: true, error: ''}; this.cart.push(item); } this.query = ''; this.results = []; this.searched = false; this.validateItem(item); if (!item.error) await this.sync(item); },
            async sync(item, acknowledge = false) { this.validateItem(item); if (item.error) return; const response = await this.request(`{{ url('/pos/cart') }}/${item.id}`, 'PUT', {quantity: item.quantity, acknowledge_price: acknowledge}); if (response.ok) { Object.assign(item, await response.json(), {error: ''}); await this.updateRaffleQuote(); } },
            async acknowledgePrice(item) { await this.sync(item, true); },
            async remove(index) { const item = this.cart[index]; const response = await this.request(`{{ url('/pos/cart') }}/${item.id}`, 'DELETE'); if (response.ok) { this.cart.splice(index, 1); await this.updateRaffleQuote(); } },
            openClearModal() { if (this.cart.length) this.clearModalOpen = true; },
            async clearCart() { if (!this.cart.length || this.clearingCart) { this.clearModalOpen = false; return; } this.clearingCart = true; const response = await this.request(`{{ route('pos.cart.clear') }}`, 'DELETE'); if (response.ok) { this.cart = []; this.cashReceived = ''; this.cashAmount = ''; this.qrAmount = ''; this.clearModalOpen = false; await this.updateRaffleQuote(); } this.clearingCart = false; },
            async updateRaffleQuote() { const response = await fetch(`{{ route('pos.raffle.quote') }}`, {headers: {'Accept':'application/json'}}); if (response.ok) { const quote = await response.json(); this.raffleTickets = quote.ticket_count; if (!this.raffleTickets) this.resetRaffle(); } },
            openRaffleModal() { this.raffleModalOpen = true; }, declineRaffle() { this.raffleDecision = 'decline'; this.raffleModalOpen = false; }, changeRaffleDecision() { this.raffleDecision = ''; },
            resetRaffle() { this.raffleDecision=''; this.raffleModalOpen=false; this.selectedCustomer=null; this.customerFullName=''; this.customerPhone=''; this.customerCi=''; }, closeModals() { this.clearModalOpen=false; this.raffleModalOpen=false; },
            async searchCustomers() { this.selectedCustomer=null; this.customerSearched=this.customerQuery.trim().length>0; if(!this.customerSearched){this.customerResults=[];return} const response=await fetch(`{{ route('customers.search') }}?search=${encodeURIComponent(this.customerQuery)}`,{headers:{'Accept':'application/json'}}); this.customerResults=response.ok?await response.json():[]; },
            selectCustomer(customer) { this.selectedCustomer=customer; this.customerQuery=customer.full_name; this.customerResults=[]; }, confirmExistingCustomer() { if(!this.selectedCustomer)return; this.raffleDecision='participate'; this.raffleModalOpen=false; }, startNewCustomer() { this.customerMode='new'; this.selectedCustomer=null; this.customerFormError=''; }, confirmNewCustomer() { if(!this.customerFullName.trim() || !this.customerPhone.trim()){this.customerFormError='Nombre completo y teléfono son obligatorios.';return} this.customerFormError=''; this.raffleDecision='participate'; this.raffleModalOpen=false; },
            request(url, method, data = null) { return fetch(url, {method, headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'}, body: data === null ? null : JSON.stringify(data)}); },
            validateItem(item) { const value = Number(item.quantity); item.error = value <= 0 ? 'Cantidad inválida.' : value > Number(item.stock) ? `Solo hay ${item.stock_label} disponibles.` : (item.is_unit && !Number.isInteger(value)) ? 'La unidad requiere una cantidad entera.' : ''; },
            effectivePrice(item) { return item.price_changed ? item.observed_price : item.price; },
            lineTotal(item) { return Math.round(Number(item.quantity || 0) * Number(this.effectivePrice(item)) * 100) / 100; },
            money(value) { return `Bs ${Number(value || 0).toFixed(2)}`; },
            get total() { return this.cart.reduce((sum, item) => sum + this.lineTotal(item), 0); }, get change() { return Math.max(0, Number(this.cashReceived || 0) - this.total); },
            get canSubmit() { return this.cart.length > 0 && !this.cart.some(i => i.error || i.price_changed || !i.available) && (this.raffleTickets === 0 || ['participate', 'decline'].includes(this.raffleDecision)) && (this.payment !== 'cash' || Number(this.cashReceived) >= this.total) && (this.payment !== 'mixed' || Math.abs(Number(this.cashAmount || 0) + Number(this.qrAmount || 0) - this.total) < 0.001); }
        }}
    </script>
</x-app-layout>
