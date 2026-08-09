<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Category::class);

        $search = trim((string) $request->query('search'));
        $categories = Category::query()
            ->where('branch_id', $request->user()->branch_id)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('categories.index', compact('categories', 'search'));
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create([
            'branch_id' => $request->user()->branch_id,
            'name' => $request->validated('name'),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('categories.index')->with('status', 'Categoría creada correctamente.');
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('categories.edit', compact('category'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update(['name' => $request->validated('name')]);

        return redirect()->route('categories.index')->with('status', 'Categoría actualizada correctamente.');
    }

    public function toggle(Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update(['is_active' => ! $category->is_active]);

        $message = $category->is_active ? 'Categoría activada correctamente.' : 'Categoría desactivada correctamente.';

        return redirect()->route('categories.index')->with('status', $message);
    }
}
