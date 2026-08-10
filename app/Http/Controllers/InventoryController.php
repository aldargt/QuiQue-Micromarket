<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InventoryMovement::class);

        $filters = [
            'search' => trim((string) $request->query('search')),
            'category' => $request->integer('category') ?: null,
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null,
            'stock' => in_array($request->query('stock'), ['zero', 'low'], true) ? $request->query('stock') : null,
            'expiration' => in_array($request->query('expiration'), ['expiring', 'expired'], true) ? $request->query('expiration') : null,
        ];

        $products = Product::query()
            ->with('category')
            ->where('branch_id', $request->user()->branch_id)
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('internal_code', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'], fn ($query, $category) => $query->where('category_id', $category))
            ->when($filters['status'], fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->when($filters['stock'] === 'zero', fn ($query) => $query->where('stock', 0))
            ->when($filters['stock'] === 'low', fn ($query) => $query
                ->where('stock', '>', 0)
                ->where('minimum_stock', '>', 0)
                ->whereColumn('stock', '<=', 'minimum_stock'))
            ->when($filters['expiration'] === 'expiring', fn ($query) => $query
                ->whereBetween('expires_at', [today(), today()->addDays(7)]))
            ->when($filters['expiration'] === 'expired', fn ($query) => $query
                ->whereDate('expires_at', '<', today()))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('inventory.index', [
            'products' => $products,
            'categories' => Category::query()
                ->where('branch_id', $request->user()->branch_id)
                ->orderBy('name')
                ->get(),
            'filters' => $filters,
        ]);
    }
}
