<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetCashierPasswordRequest;
use App\Http\Requests\StoreCashierRequest;
use App\Http\Requests\UpdateCashierRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with(['role', 'branch'])
            ->whereHas('role', fn ($query) => $query->where('slug', RoleSlug::Cashier->value))
            ->latest()
            ->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', ['branches' => $this->activeBranches()]);
    }

    public function store(StoreCashierRequest $request): RedirectResponse
    {
        $cashierRole = Role::query()->where('slug', RoleSlug::Cashier->value)->firstOrFail();

        User::query()->create([
            ...$request->safe()->only(['name', 'email', 'branch_id', 'password']),
            'role_id' => $cashierRole->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Cajero creado correctamente.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', [
            'cashier' => $user->load(['role', 'branch']),
            'branches' => $this->activeBranches(),
        ]);
    }

    public function update(UpdateCashierRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return redirect()->route('admin.users.edit', $user)->with('status', 'Datos del cajero actualizados.');
    }

    public function resetPassword(ResetCashierPasswordRequest $request, User $user): RedirectResponse
    {
        $user->update(['password' => $request->validated('password')]);

        return redirect()->route('admin.users.edit', $user)->with('status', 'Contraseña restablecida correctamente.');
    }

    /** @return Collection<int, Branch> */
    private function activeBranches(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get();
    }
}
