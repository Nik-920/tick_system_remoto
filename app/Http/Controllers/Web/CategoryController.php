<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListCategoriesRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(ListCategoriesRequest $request): View
    {
        $this->authorize('create', Category::class);

        $filters = $request->validated();
        $query = Category::query()->withCount(['tickets', 'incidentHistory']);

        $this->applyFilters($query, $filters);

        $categories = $query
            ->latest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return view('categories.index', [
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::query()->create($request->validated());

        return redirect()
            ->route('categories.edit', $category)
            ->with('status', 'Categoria creada correctamente.');
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        $category->loadCount(['tickets', 'incidentHistory']);

        return view('categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->fill($request->validated());
        $category->save();

        return redirect()
            ->route('categories.edit', $category)
            ->with('status', 'Categoria actualizada correctamente.');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
    }
}
