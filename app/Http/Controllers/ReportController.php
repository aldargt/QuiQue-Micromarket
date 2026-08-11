<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Sale;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(ReportRequest $request, ReportService $reports): View
    {
        Gate::authorize('viewAny', Sale::class);

        return view('reports.index', $reports->forBranch(
            $request->user()->branch_id,
            $request->validated(),
        ));
    }

    public function pdf(ReportRequest $request, ReportService $reports): Response
    {
        Gate::authorize('export', Sale::class);
        $data = $reports->forBranch($request->user()->branch_id, $request->validated(), false);
        $data['branchName'] = $request->user()->branch->name;
        $data['generatedAt'] = now(config('app.timezone'));

        return Pdf::loadView('reports.pdf', $data)
            ->setPaper('letter', 'landscape')
            ->download('Reporte-Ventas-'.now(config('app.timezone'))->format('Ymd-His').'.pdf');
    }
}
