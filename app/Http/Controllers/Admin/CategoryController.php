<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class CategoryController
{
    public function __construct(private StoreContext $storeContext) {}

    public function index()
    {
        $store = $this->storeContext->get();

        return Category::where('store_id', $store->id)
            ->orderBy('sort_order')
            ->get();
    }

    public function store(Request $request)
    {
        $store = $this->storeContext->get();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ]);

        $category = Category::create([
            ...$validated,
            'store_id' => $store->id,
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        if ($category->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }

        $category->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ]));

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        if ($category->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }

        $category->delete(); // soft delete

        return response()->noContent();
    }
}
