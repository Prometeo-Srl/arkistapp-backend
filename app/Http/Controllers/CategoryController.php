<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Company;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $this->authorize('view', $company);

        return CategoryResource::collection(
            $company->categories()
                ->with(['rootFolders'])
                ->orderBy('position')
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StoreCategoryRequest $request, Company $company)
    {
        $this->authorize('create', [Category::class, $company]);

        $category = $company->categories()->create([
            ...$request->validated(),
            'created_by_id' => $request->user()->getKey(),
        ]);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $this->authorize('update', $category);

        $category->update($request->safe()->only(['name', 'icon', 'color', 'position']));

        return new CategoryResource($category->fresh());
    }

    public function destroy(Request $request, Category $category)
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->noContent();
    }
}
