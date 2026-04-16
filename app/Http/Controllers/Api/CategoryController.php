<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListCategoriesRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(ListCategoriesRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        $filters = $request->validated();
        $query = Category::query()->withCount(['tickets', 'incidentHistory']);

        $this->applyFilters($query, $filters);

        $categories = $query
            ->latest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return CategoryResource::collection($categories);
    }

    public function show(Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        $category->loadCount(['tickets', 'incidentHistory']);

        return new CategoryResource($category);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $data = $request->validated();

        $category = Category::query()->create([
            'name' => (string) $data['name'],
            'icon' => $data['icon'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        $category->loadCount(['tickets', 'incidentHistory']);

        return response()->json([
            'message' => 'Categoria creada correctamente.',
            'data' => (new CategoryResource($category))->resolve($request),
        ], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->fill($request->validated());
        $category->save();

        $category->loadCount(['tickets', 'incidentHistory']);

        return response()->json([
            'message' => 'Categoria actualizada correctamente.',
            'data' => (new CategoryResource($category))->resolve($request),
        ]);
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
