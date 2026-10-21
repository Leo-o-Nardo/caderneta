<?php

namespace App\Http\Controllers;

use App\Enums\CategoryKind;
use App\Http\Requests\Finance\ReorderCategoriesRequest;
use App\Http\Requests\Finance\StoreCategoryRequest;
use App\Http\Requests\Finance\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Todas as consultas daqui já saem limitadas à casa atual pelo BelongsToHousehold. */
class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['kind' => ['nullable', Rule::enum(CategoryKind::class)]]);

        $categories = Category::active()
            ->when($request->query('kind'), fn ($query, $kind) => $query->where('kind', $kind))
            ->orderBy('kind')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return CategoryResource::collection($categories);
    }

    /** A ordem escolhida pela casa: vale na lista, no seletor dos lançamentos e para o assistente. */
    public function reorder(ReorderCategoriesRequest $request): Response
    {
        Category::reorder($request->enum('kind', CategoryKind::class), $request->validated('ids'));

        return response()->noContent();
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category);
    }

    /**
     * Arquiva em vez de apagar: os lançamentos antigos continuam apontando para ela. O orçamento vai
     * embora junto, senão continuaria no Resumo preso a uma categoria que não dá mais para editar.
     */
    public function destroy(Category $category): Response
    {
        DB::transaction(function () use ($category) {
            $category->forceFill(['archived_at' => now()])->save();
            $category->budget()->delete();
        });

        return response()->noContent();
    }
}
