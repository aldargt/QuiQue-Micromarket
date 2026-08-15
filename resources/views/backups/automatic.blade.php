<x-app-layout>
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-gray-900/60 p-4" x-data="automaticBackup()" x-init="run()" role="dialog" aria-modal="true">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <template x-if="loading"><div class="py-4 text-center"><svg class="mx-auto h-10 w-10 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg><h2 class="mt-4 text-lg font-semibold">Realizando copia de seguridad</h2><p class="mt-2 text-sm text-gray-600">Se está generando una copia de seguridad automática del sistema. Por favor, espere...</p></div></template>
            <template x-if="!loading"><div><h2 class="text-lg font-semibold" x-text="result.title"></h2><p class="mt-3 text-sm text-gray-600" x-text="result.message"></p><div class="mt-6 flex justify-end"><a :href="result.redirect || @js($returnUrl)" class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white">Aceptar</a></div></div></template>
        </div>
    </div>
    <script>function automaticBackup(){return{loading:true,result:{},async run(){try{const response=await fetch(@js(route('backup.automatic.store')),{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':@js(csrf_token())}});this.result=response.ok?await response.json():{title:'Copia de seguridad',message:'No se pudo completar la copia de seguridad.',redirect:@js($returnUrl)}}catch(error){this.result={title:'Copia de seguridad',message:'No se pudo completar la copia de seguridad.',redirect:@js($returnUrl)}}this.loading=false}}}</script>
</x-app-layout>
