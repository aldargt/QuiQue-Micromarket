<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptRaffleParticipationRequest;
use App\Models\Sale;
use App\Services\RaffleParticipationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RaffleParticipationController extends Controller
{
    public function accept(AcceptRaffleParticipationRequest $request, Sale $sale, RaffleParticipationService $service): RedirectResponse
    {
        Gate::authorize('view', $sale);
        $participation = $service->accept($request->user(), $sale, $request->validated());

        return back()->with('status', $participation->tickets->count().' ticket(s) asignado(s) correctamente.');
    }

    public function decline(Request $request, Sale $sale, RaffleParticipationService $service): RedirectResponse
    {
        Gate::authorize('view', $sale);
        $service->decline($request->user(), $sale);

        return back()->with('status', 'La venta quedó registrada sin participación en el sorteo.');
    }
}
