<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Sale::class);
        $search = trim((string) $request->query('search'));
        $sales = Sale::query()->with(['user', 'payments'])->where('branch_id', $request->user()->branch_id)
            ->when($search, fn ($q) => $q->where('sale_number', 'like', "%{$search}%"))->latest('confirmed_at')->paginate(20)->withQueryString();

        return view('sales.index', compact('sales', 'search'));
    }

    public function show(Sale $sale): View
    {
        Gate::authorize('view', $sale);

        return view('sales.show', ['sale' => $sale->load(['user', 'items.product', 'payments'])]);
    }
}
