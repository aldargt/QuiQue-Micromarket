<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        Gate::authorize('viewAny', Sale::class);

        return view('dashboard', $dashboard->forBranch($request->user()->branch_id, today()));
    }
}
