<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Services\PosCartService;
use App\Services\RaffleParticipationService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(Request $request, PosCartService $cart, RaffleParticipationService $raffles): View
    {
        Gate::authorize('create', Sale::class);

        $threshold = (string) $request->user()->branch->raffle_ticket_threshold;

        return view('pos.index', ['initialCart' => $cart->items($request->user()), 'initialRaffleQuote' => $raffles->ticketCount($cart->total($request->user()), $threshold)]);
    }

    public function raffleQuote(Request $request, PosCartService $cart, RaffleParticipationService $raffles): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $threshold = (string) $request->user()->branch->raffle_ticket_threshold;

        return response()->json(['ticket_count' => $raffles->ticketCount($cart->total($request->user()), $threshold)]);
    }

    public function search(Request $request): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $search = trim((string) $request->query('search'));
        if (mb_strlen($search) < 1) {
            return response()->json([]);
        }
        $products = Product::query()->where('branch_id', $request->user()->branch_id)->where('is_active', true)->where('stock', '>', 0)
            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('barcode', 'like', "%{$search}%")->orWhere('internal_code', 'like', "%{$search}%"))
            ->orderByRaw('CASE WHEN barcode = ? OR internal_code = ? THEN 0 ELSE 1 END', [$search, $search])->orderBy('name')->limit(12)->get();

        return response()->json($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'code' => $p->displayCode(), 'exact_code_match' => $p->barcode === $search || $p->internal_code === $search, 'unit' => $p->unit->label(), 'is_unit' => $p->unit->value === 'unit', 'price' => $p->sale_price, 'stock' => $p->stock, 'stock_label' => $p->unit->formatQuantity($p->stock)]));
    }

    public function store(StoreSaleRequest $request, SaleService $service, RaffleParticipationService $raffles): RedirectResponse
    {
        $data = $request->validated();
        $cart = app(PosCartService::class);
        $items = $cart->checkoutItems($request->user(), $data['items']);
        $ticketCount = $raffles->ticketCount($cart->total($request->user()), (string) $request->user()->branch->raffle_ticket_threshold);
        if ($ticketCount > 0 && ! in_array($data['raffle_decision'] ?? null, ['participate', 'decline'], true)) {
            throw ValidationException::withMessages(['raffle_decision' => 'Indique si el cliente participará en el sorteo antes de confirmar la venta.']);
        }
        $sale = $service->confirm($request->user(), $items, $data['payment_type'], $data['cash_received'] ?? null, $data['cash_amount'] ?? null, $data['qr_amount'] ?? null);
        if ($sale->raffleParticipation !== null) {
            if (($data['raffle_decision'] ?? null) === 'participate') {
                $raffles->accept($request->user(), $sale, [
                    'customer_id' => $data['customer_id'] ?? null,
                    'full_name' => $data['customer_full_name'] ?? null,
                    'phone' => $data['customer_phone'] ?? null,
                    'ci' => $data['customer_ci'] ?? null,
                ]);
            } else {
                $raffles->decline($request->user(), $sale);
            }
        }
        $cart->clear($request->user());

        return redirect()->route('sales.show', $sale)->with('status', "Venta {$sale->sale_number} confirmada correctamente.");
    }

    public function updateCart(Request $request, Product $product, PosCartService $cart): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $data = $request->validate(['quantity' => ['required', 'decimal:0,3', 'gt:0', 'lte:999999999.999'], 'acknowledge_price' => ['sometimes', 'boolean']]);

        return response()->json($cart->put($request->user(), $product, (string) $data['quantity'], (bool) ($data['acknowledge_price'] ?? false)));
    }

    public function removeCartItem(Request $request, Product $product, PosCartService $cart): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $cart->remove($request->user(), $product);

        return response()->json([], 204);
    }

    public function clearCart(Request $request, PosCartService $cart): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $cart->clear($request->user());

        return response()->json([], 204);
    }
}
