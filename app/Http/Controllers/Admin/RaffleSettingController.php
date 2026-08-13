<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RaffleSettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_if($request->user()->branch === null, 403, 'El usuario no tiene una sucursal asignada.');

        $data = $request->validate(['raffle_ticket_threshold' => ['required', 'integer', 'min:1', 'max:9999999999']]);
        $request->user()->branch->update($data);

        return redirect()->route('customers.index')->with('status', 'Umbral de tickets actualizado correctamente.');
    }
}
