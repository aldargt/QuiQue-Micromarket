<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Services\DashboardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        Gate::authorize('viewAny', Sale::class);

        return view('dashboard', $dashboard->forBranch($request->user()->branch_id, today()));
    }

    public function inventoryPdf(Request $request, DashboardService $dashboard): Response
    {
        Gate::authorize('export', Product::class);
        $data = $dashboard->forBranch($request->user()->branch_id, today());
        $data['branchName'] = $request->user()->branch->name;
        $data['generatedAt'] = now(config('app.timezone'));

        return Pdf::loadView('dashboard-inventory-pdf', $data)
            ->setPaper('letter', 'landscape')
            ->download('Inventario-Abastecimiento-'.now(config('app.timezone'))->format('Ymd-His').'.pdf');
    }
}
