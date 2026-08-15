<?php

namespace App\Http\Controllers;

use App\Data\BackupResult;
use App\Enums\RoleSlug;
use App\Services\AutomaticBackupService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutomaticBackupController extends Controller
{
    public function show(Request $request, AutomaticBackupService $backups): View|RedirectResponse
    {
        if (! $backups->isDue()) {
            return redirect()->route('dashboard');
        }

        return view('backups.automatic', ['returnUrl' => $request->session()->get('backup.return_url', route('dashboard'))]);
    }

    public function store(Request $request, AutomaticBackupService $backups): JsonResponse
    {
        $result = $backups->runIfDue();
        $administrator = $request->user()->hasAnyRole([RoleSlug::Administrator->value]);

        return response()->json([...$this->message($result, $administrator), 'redirect' => $request->session()->pull('backup.return_url', route('dashboard'))]);
    }

    /** @return array{status:string,title:string,message:string} */
    private function message(?BackupResult $result, bool $administrator): array
    {
        if ($result === null) {
            return ['status' => 'skipped', 'title' => 'Copia de seguridad', 'message' => 'No es necesario realizar una nueva copia de seguridad en este momento.'];
        }
        if (! $administrator) {
            return $result->isSuccessful()
                ? ['status' => 'success', 'title' => 'Copia de seguridad', 'message' => 'Se realizó correctamente la copia de seguridad del sistema.']
                : ['status' => 'failed', 'title' => 'Copia de seguridad', 'message' => 'No se pudo completar la copia de seguridad. El sistema volverá a intentarlo posteriormente.'];
        }

        if ($result->isSuccessful()) {
            $completedAt = $result->completedAt ? Carbon::parse($result->completedAt)->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s');

            return [
                'status' => 'success',
                'title' => 'Copia de seguridad automática',
                'message' => "Se realizó correctamente la copia de seguridad automática del sistema.\n\nRuta: ".dirname((string) $result->path)."\nArchivo: {$result->filename}\nFecha y hora: {$completedAt}",
            ];
        }

        return ['status' => 'failed', 'title' => 'No se pudo realizar la copia de seguridad.', 'message' => 'No fue posible completar el backup local. El sistema volverá a intentarlo posteriormente.'];
    }
}
