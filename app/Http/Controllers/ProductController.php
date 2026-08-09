<?php

namespace App\Http\Controllers;

use App\Enums\MeasurementUnit;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Services\ProductBarcodeGuard;
use App\Services\ProductCodeGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $filters = [
            'search' => trim((string) $request->query('search')),
            'category' => $request->integer('category') ?: null,
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null,
            'stock' => $request->query('stock') === 'zero' ? 'zero' : null,
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
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => $this->categoriesFor($request, includeInactive: true),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Product::class);

        return view('products.create', [
            'categories' => $this->categoriesFor($request),
            'units' => MeasurementUnit::cases(),
        ]);
    }

    public function store(
        StoreProductRequest $request,
        ProductCodeGenerator $codeGenerator,
        ProductBarcodeGuard $barcodeGuard,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $codeGenerator, $barcodeGuard): void {
            Branch::query()->whereKey($request->user()->branch_id)->lockForUpdate()->firstOrFail();
            $barcodeGuard->ensureAvailable($request->user()->branch_id, $request->validated('barcode'));

            Product::query()->create([
                ...$request->validated(),
                'branch_id' => $request->user()->branch_id,
                'internal_code' => $request->validated('barcode') === null ? $codeGenerator->generate() : null,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('products.index')->with('status', 'Producto creado correctamente.');
    }

    public function edit(Request $request, Product $product): View
    {
        Gate::authorize('update', $product);

        $categories = $this->categoriesFor($request);
        if (! $product->category->is_active) {
            $categories->prepend($product->category);
        }

        return view('products.edit', [
            'product' => $product->load(['priceHistory.user']),
            'categories' => $categories,
            'units' => MeasurementUnit::cases(),
            'returnToPos' => $request->query('return') === 'pos',
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        ProductBarcodeGuard $barcodeGuard,
        ProductCodeGenerator $codeGenerator,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $product, $barcodeGuard, $codeGenerator): void {
            Branch::query()->whereKey($product->branch_id)->lockForUpdate()->firstOrFail();
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($lockedProduct->is_active) {
                $barcodeGuard->ensureAvailable($lockedProduct->branch_id, $request->validated('barcode'), $lockedProduct);
            }

            $internalCode = match (true) {
                $request->validated('barcode') !== null => null,
                $lockedProduct->internal_code !== null => $lockedProduct->internal_code,
                default => $codeGenerator->generate(),
            };

            $oldPrice = $lockedProduct->sale_price;
            $lockedProduct->update([
                ...$request->validated(),
                'internal_code' => $internalCode,
            ]);

            if (bccomp($oldPrice, $lockedProduct->sale_price, 2) !== 0) {
                ProductPriceHistory::query()->create([
                    'branch_id' => $lockedProduct->branch_id, 'product_id' => $lockedProduct->id,
                    'user_id' => $request->user()->id, 'old_price' => $oldPrice, 'new_price' => $lockedProduct->sale_price,
                ]);
            }
        });

        if ($request->input('return_to') === 'pos') {
            return redirect()->route('pos.index')->with('status', 'Producto actualizado. Revise el precio en el carrito.');
        }

        return redirect()->route('products.index')->with('status', 'Producto actualizado correctamente.');
    }

    public function toggle(Product $product, ProductBarcodeGuard $barcodeGuard): RedirectResponse
    {
        Gate::authorize('update', $product);

        DB::transaction(function () use ($product, $barcodeGuard): void {
            Branch::query()->whereKey($product->branch_id)->lockForUpdate()->firstOrFail();
            if (! $product->is_active) {
                $barcodeGuard->ensureAvailable($product->branch_id, $product->barcode, $product);
            }
            $product->update(['is_active' => ! $product->is_active]);
        });

        $message = $product->is_active ? 'Producto activado correctamente.' : 'Producto desactivado correctamente.';

        return redirect()->route('products.index')->with('status', $message);
    }

    /** @return Collection<int, Category> */
    private function categoriesFor(Request $request, bool $includeInactive = false): Collection
    {
        return Category::query()
            ->where('branch_id', $request->user()->branch_id)
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();
    }
}
