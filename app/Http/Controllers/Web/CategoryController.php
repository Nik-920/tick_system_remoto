<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListCategoriesRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Storage\CategoryIconStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

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

    public function store(StoreCategoryRequest $request, CategoryIconStorageService $iconStorage): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $data = $request->validated();
        $iconFile = $request->file('icon_file');

        $category = DB::transaction(function () use ($data, $iconFile, $iconStorage): Category {
            $category = Category::query()->create([
                'name' => $data['name'],
                'icon' => $data['icon'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            if ($iconFile instanceof UploadedFile) {
                $category->icon = $iconStorage->replaceIcon($category, $iconFile, $category->icon);
                $category->save();
            }

            return $category;
        });

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

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        CategoryIconStorageService $iconStorage
    ): RedirectResponse {
        $this->authorize('update', $category);

        $payload = $request->validated();
        unset($payload['icon_file']);

        $iconFile = $request->file('icon_file');

        DB::transaction(function () use ($category, $payload, $iconFile, $iconStorage): void {
            $previousIcon = is_string($category->icon) ? $category->icon : null;

            $category->fill($payload);

            if ($iconFile instanceof UploadedFile) {
                $category->icon = $iconStorage->replaceIcon($category, $iconFile, $previousIcon);
            }

            $category->save();
        });

        return redirect()
            ->route('categories.edit', $category)
            ->with('status', 'Categoria actualizada correctamente.');
    }

    public function destroy(Category $category, CategoryIconStorageService $iconStorage): RedirectResponse
    {
        $this->authorize('delete', $category);

        $categoryId = $category->id;
        $iconUrl = is_string($category->icon) ? $category->icon : null;

        DB::transaction(function () use ($category): void {
            $category->delete();
        });

        try {
            $iconStorage->deleteIcon($iconUrl);
        } catch (Throwable $exception) {
            Log::warning('No fue posible eliminar el icono de categoria en storage.', [
                'category_id' => $categoryId,
                'icon_url' => $iconUrl,
                'error' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('categories.index')
            ->with('status', 'Categoria eliminada correctamente.');
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
