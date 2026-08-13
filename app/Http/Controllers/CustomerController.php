<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\RafflePeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);
        $search = trim((string) $request->query('search'));
        $customers = $this->query($request, $search)->withCount('tickets')->orderByDesc('tickets_count')->orderBy('full_name')->paginate(20)->withQueryString();

        return view('customers.index', ['customers' => $customers, 'search' => $search, 'branch' => $request->user()->branch]);
    }

    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);
        $search = trim((string) $request->query('search'));
        if (mb_strlen($search) < 1) {
            return response()->json([]);
        }

        return response()->json($this->query($request, $search)->orderBy('full_name')->limit(10)->get(['id', 'full_name', 'phone', 'ci']));
    }

    public function show(Request $request, Customer $customer, RafflePeriodService $periods): View
    {
        Gate::authorize('view', $customer);
        $periods->expirePastTickets($customer->branch_id, now());
        $periodId = $request->integer('period') ?: null;
        $tickets = $customer->tickets()->with(['sale', 'period'])->when($periodId, fn ($q) => $q->where('raffle_period_id', $periodId))->latest()->paginate(30)->withQueryString();
        $periods = $customer->tickets()->with('period')->get()->pluck('period')->unique('id')->sortByDesc('starts_on')->values();

        return view('customers.show', compact('customer', 'tickets', 'periods', 'periodId'));
    }

    private function query(Request $request, string $search)
    {
        return Customer::query()->where('branch_id', $request->user()->branch_id)->when($search, fn ($q) => $q->where(fn ($inner) => $inner->where('full_name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('ci', 'like', "%{$search}%")));
    }
}
