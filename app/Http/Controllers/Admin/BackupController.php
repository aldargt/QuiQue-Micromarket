<?php

namespace App\Http\Controllers\Admin;

use App\Data\BackupResult;
use App\Http\Controllers\Controller;
use App\Services\BackupManager;
use Illuminate\Http\JsonResponse;

class BackupController extends Controller
{
    public function store(BackupManager $manager): JsonResponse
    {
        return response()->json($this->message($manager->run()));
    }

    /** @return array{status:string,title:string,message:string} */
    private function message(BackupResult $result): array
    {
        if ($result->isSuccessful()) {
            return [
                'status' => 'success',
                'title' => 'Backup realizado correctamente.',
                'message' => "El respaldo se guardó en:\n".dirname((string) $result->path)."\n\nArchivo: {$result->filename}",
            ];
        }

        return ['status' => 'failed', 'title' => 'No se pudo realizar la copia de seguridad.', 'message' => 'No fue posible completar el backup local. Inténtelo nuevamente o contacte al responsable técnico.'];
    }
}
