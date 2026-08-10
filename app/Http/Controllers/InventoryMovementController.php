<?php

namespace App\Http\Controllers;

use App\Enums\InventoryMovementType;
use App\Http\Requests\StoreInventoryMovementRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryMovementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InventoryMovement::class);

        $type = InventoryMovementType::tryFrom((string) $request->query('type'));
        $productId = $request->integer('product') ?: null;
        $search = trim((string) $request->query('search'));

        $movements = InventoryMovement::query()
            ->with(['product', 'user'])
            ->where('branch_id', $request->user()->branch_id)
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->when($productId, fn ($query) => $query->where('product_id', $productId))
            ->when($search, fn ($query) => $query->whereHas('product', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('internal_code', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('inventory.movements.index', [
            'movements' => $movements,
            'types' => InventoryMovementType::cases(),
            'filters' => ['type' => $type?->value, 'product' => $productId, 'search' => $search],
        ]);
    }

    public function create(Product $product): View
    {
        Gate::authorize('create', [InventoryMovement::class, $product]);

        return view('inventory.movements.create', [
            'product' => $product->load('category'),
            'types' => InventoryMovementType::cases(),
        ]);
    }

    public function store(
        StoreInventoryMovementRequest $request,
        Product $product,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $data = $request->validated();
        $movement = $inventoryService->record(
            $request->user(),
            $product,
            InventoryMovementType::from($data['type']),
            (string) $data['quantity'],
            $data['reason'],
            $data['observation'] ?? null,
        );

        return redirect()->route('inventory.movements.index', ['product' => $product->id])
            ->with('status', 'Movimiento registrado. Nuevo stock: '.$movement->product->unit->formatQuantity($movement->stock_after).'.');
    }
}
